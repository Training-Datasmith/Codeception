<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Actions as ActionsGenerator;
use Codeception\Lib\Generator\Actor as ActorGenerator;
use function implode;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface as SymfonyOutputInterface;
/**
 * Generates Actor classes (initially Guy classes) from suite configs.
 * Starting from Codeception 2.0 actor classes are auto-generated. Use this command to generate them manually.
 *
 * * `codecept build`
 * * `codecept build path/to/project`
 *
 */
#[As_Command(name: 'build', description: 'Generates base classes for all suites')]
class Build extends Command
{
    use Shared\Config_Trait;
    use Shared\File_System_Trait;
    protected string $inherited_method_template = ' * @method void %s(%s)';
    protected ?Symfony_Output_Interface $output = null;
    protected function execute(Input_Interface $input, Symfony_Output_Interface $output): int
    {
        $this->output = $output;
        $this->build_actors_for_config();
        return Command::SUCCESS;
    }
    private function build_actor(array $settings): bool
    {
        $actor_generator = new Actor_Generator($settings);
        $this->output->writeln('<info>' . Configuration::config()['namespace'] . '\\' . $actor_generator->get_actor_name() . '</info> includes modules: ' . implode(', ', $actor_generator->get_modules()));
        $content = $actor_generator->produce();
        $file = $this->create_directory_for(Configuration::support_dir(), $settings['actor']) . $this->get_short_class_name($settings['actor']);
        $file .= '.php';
        return $this->create_file($file, $content);
    }
    private function build_actions(array $settings): bool
    {
        $actions_generator = new Actions_Generator($settings);
        $content = $actions_generator->produce();
        $this->output->writeln(sprintf(' -> %sActions.php generated successfully. ', $settings['actor']) . $actions_generator->get_num_methods() . ' methods added');
        $file = $this->create_directory_for(Configuration::support_dir() . '_generated', $settings['actor']);
        $file .= $this->get_short_class_name($settings['actor']) . 'Actions.php';
        return $this->create_file($file, $content, true);
    }
    private function build_suite_actors(): void
    {
        $suites = $this->get_suites();
        if ($suites !== []) {
            $this->output->writeln('<info>Building Actor classes for suites: ' . implode(', ', $suites) . '</info>');
        }
        foreach ($suites as $suite) {
            $settings = $this->get_suite_config($suite);
            if (!$settings['actor']) {
                continue;
                // no actor
            }
            $this->build_actions($settings);
            $actor_built = $this->build_actor($settings);
            if ($actor_built) {
                $this->output->writeln($settings['actor'] . '.php created.');
            }
        }
    }
    protected function build_actors_for_config(?string $config_file = null): void
    {
        $config = $this->get_global_config($config_file);
        $dir = Configuration::project_dir();
        $this->build_suite_actors();
        foreach ($config['include'] as $sub_config) {
            $this->output->writeln("\n<comment>Included Configuration: {$sub_config}</comment>");
            $this->build_actors_for_config($dir . DIRECTORY_SEPARATOR . $sub_config);
        }
    }
}