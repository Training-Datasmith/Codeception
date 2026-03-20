<?php

declare (strict_types=1);
namespace Codeception;

use Codeception\Command\Shared\File_System_Trait;
use Codeception\Command\Shared\Style_Trait;
use Codeception\Lib\Generator\Actions;
use Codeception\Lib\Generator\Actor;
use Codeception\Lib\Generator\Helper;
use Codeception\Lib\Module_Container;
use Exception;
use Symfony\Component\Console\Helper\Question_Helper;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Choice_Question;
use Symfony\Component\Console\Question\Confirmation_Question;
use Symfony\Component\Console\Question\Question;
/**
 * Codeception templates allow creating a customized setup and configuration for your project.
 * An abstract class for installation template. Each init template should extend it and implement a `setup` method.
 * Use it to build a custom setup class which can be started with `codecept init` command.
 *
 *
 * ```php
 * <?php
 * namespace Codeception\Template; // it is important to use this namespace so codecept init could locate this template
 * class CustomInstall extends \Codeception\InitTemplate
 * {
 *      public function setup()
 *      {
 *         // implement this
 *      }
 * }
 * ```
 * This class provides various helper methods for building customized setup
 */
abstract class Init_Template
{
    use File_System_Trait;
    use Style_Trait;
    /**
     * @var string
     */
    public const GIT_IGNORE = '.gitignore';
    protected string $namespace = 'Tests';
    protected string $actor_suffix = 'Tester';
    protected string $support_namespace = 'Support';
    protected string $work_dir = '.';
    protected Output_Interface $output;
    public function __construct(protected Input_Interface $input, Output_Interface $output)
    {
        $this->add_styles($output);
        $this->output = $output;
    }
    /**
     * Change the directory where Codeception should be installed.
     */
    public function init_dir(string $work_dir): void
    {
        $this->check_installed($work_dir);
        $this->say_info("Initializing Codeception in {$work_dir}");
        $this->create_directory_for($work_dir);
        chdir($work_dir);
        $this->work_dir = $work_dir;
    }
    /**
     * Override this class to create customized setup.
     *
     * @return mixed
     */
    abstract public function setup();
    /**
     * ```php
     * <?php
     * // propose firefox as default browser
     * $this->ask('select the browser of your choice', 'firefox');
     *
     * // propose firefox or chrome possible options
     * $this->ask('select the browser of your choice', ['firefox', 'chrome']);
     *
     * // ask true/false question
     * $this->ask('do you want to proceed (y/n)', true);
     * ```
     *
     * @return mixed|string
     */
    protected function ask(string $question, string|bool|array|null $answer = null): mixed
    {
        $question = '? ' . $question;
        $dialog = new Question_Helper();
        if (is_array($answer)) {
            $question .= ' <info>(' . $answer[0] . ')</info> ';
            return $dialog->ask($this->input, $this->output, new Choice_Question($question, $answer, 0));
        }
        if (is_bool($answer)) {
            $question .= ' (y/n) ';
            return $dialog->ask($this->input, $this->output, new Confirmation_Question($question, $answer));
        }
        if (is_string($answer)) {
            $question .= " <info>({$answer})</info>";
        }
        return $dialog->ask($this->input, $this->output, new Question("{$question} ", $answer));
    }
    /**
     * Print a message to console.
     *
     * ```php
     * <?php
     * $this->say('Welcome to Setup');
     * ```
     */
    protected function say(string $message = ''): void
    {
        $this->output->writeln($message);
    }
    /**
     * Print a successful message
     */
    protected function say_success(string $message): void
    {
        $this->say("<notice> {$message} </notice>");
    }
    /**
     * Print error message
     */
    protected function say_error(string $message): void
    {
        $this->say("<error> {$message} </error>");
    }
    /**
     * Print warning message
     */
    protected function say_warning(string $message): void
    {
        $this->say("<warning> {$message} </warning>");
    }
    /**
     * Print info message
     */
    protected function say_info(string $message): void
    {
        $this->say("<debug> {$message}</debug>");
    }
    /**
     * Create a helper class inside a directory
     */
    protected function create_helper(string $name, string $directory, array $settings = []): void
    {
        $dir = $directory . DIRECTORY_SEPARATOR . 'Helper';
        $file = $this->create_directory_for($dir, "{$name}.php") . "{$name}.php";
        $gen = new Helper($settings, $name);
        $this->create_file($file, $gen->produce());
        require_once $file;
        $this->say_info("{$name} helper has been created in {$dir}");
    }
    /**
     * Create an empty directory and add a placeholder file into it
     */
    protected function create_empty_directory(string $dir): void
    {
        $this->create_directory_for($dir);
        $this->create_file($dir . DIRECTORY_SEPARATOR . '.gitkeep', '');
    }
    protected function git_ignore(string $path): void
    {
        file_put_contents($path . DIRECTORY_SEPARATOR . self::GIT_IGNORE, "*\n!" . self::GIT_IGNORE . "\n");
    }
    protected function check_installed(string $dir = '.'): void
    {
        if (file_exists("{$dir}/codeception.yml") || file_exists("{$dir}/codeception.dist.yml")) {
            throw new Exception('Codeception is already installed in this directory');
        }
    }
    /**
     * Create an Actor class and generate actions for it.
     * Requires a suite config as array in 3rd parameter.
     * @param array<string,mixed> $suiteConfig
     */
    protected function create_actor(string $name, string $directory, array $suite_config): void
    {
        $file = $this->create_directory_for($directory, $name) . $this->get_short_class_name($name) . '.php';
        $suite_config['namespace'] = $this->namespace;
        $config = Configuration::merge_configs(Configuration::$default_suite_settings, $suite_config);
        $actor_gen = new Actor($config);
        $this->create_file($file, $actor_gen->produce());
        $this->say_info("{$name} actor has been created in {$directory}");
        $actions_gen = new Actions($config);
        $generated = $directory . DIRECTORY_SEPARATOR . '_generated';
        $this->create_directory_for($generated, 'Actions.php');
        $this->create_file($generated . DIRECTORY_SEPARATOR . $actor_gen->get_actor_name() . 'Actions.php', $actions_gen->produce());
        $this->say_info('Actions have been loaded');
    }
    protected function add_modules_to_composer(array $modules): ?int
    {
        $packages = Module_Container::$packages;
        if (!file_exists('composer.json')) {
            $this->say('');
            $this->say_warning('Can\'t locate composer.json, please add following packages into "require-dev" section of composer.json:');
            $this->say('');
            foreach (array_unique($modules) as $module) {
                if (!isset($packages[$module])) {
                    continue;
                }
                $package = $packages[$module];
                $this->say(sprintf('"%s": "%s"', $package, '*'));
            }
            $this->say('');
            return null;
        }
        $composer = json_decode(file_get_contents('composer.json'), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($composer)) {
            throw new Exception("Invalid composer.json file. JSON can't be decoded");
        }
        $section = null;
        if (!empty($composer['require']['codeception/codeception'])) {
            $section = 'require';
        }
        if (!empty($composer['require-dev']['codeception/codeception'])) {
            $section = 'require-dev';
        }
        if ($section === null) {
            $section = 'require';
        }
        $added = 0;
        foreach (array_unique($modules) as $module) {
            if (!isset($packages[$module])) {
                continue;
            }
            $pkg = $packages[$module];
            if (isset($composer[$section][$pkg])) {
                continue;
            }
            $this->say_info("Adding {$pkg} for {$module} to composer.json");
            $composer[$section][$pkg] = '*';
            ++$added;
        }
        file_put_contents('composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        if ($added !== 0) {
            $this->say("{$added} new packages added to {$section}");
            if ($this->ask('composer.json updated. Do you want to run "composer update"?', true)) {
                $this->say_info('Running composer update');
                exec('composer update', $out, $status);
                if ($status !== 0) {
                    $this->say_info('Composer installation failed. Please check composer.json and try to run "composer update" manually');
                    return null;
                }
                $vendor = $composer['config']['vendor_dir'] ?? 'vendor';
                $this->update_composer_class_map($vendor);
            }
        }
        return $added;
    }
    private function update_composer_class_map(string $vendor_dir = 'vendor'): void
    {
        $loader = require $vendor_dir . '/autoload.php';
        $loader->add_class_map(require $vendor_dir . '/composer/autoload_classmap.php');
        $map = require $vendor_dir . '/composer/autoload_psr4.php';
        foreach ($map as $namespace => $paths) {
            $loader->set_psr4($namespace, $paths);
        }
    }
}