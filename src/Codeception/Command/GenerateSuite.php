<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Actor as ActorGenerator;
use Codeception\Util\Template;
use Exception;
use function file_exists;
use function preg_match;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Yaml\Yaml;
use function ucfirst;
/**
 * Create new test suite. Requires suite name and actor name
 *
 * * ``
 * * `codecept g:suite Api` -> api + ApiTester
 * * `codecept g:suite Integration Code` -> integration + CodeTester
 * * `codecept g:suite Frontend Front` -> frontend + FrontTester
 *
 */
#[As_Command(name: 'generate:suite', description: 'Generates new test suite')]
class Generate_Suite extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    use Shared\Style_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'suite to be generated')->add_argument('actor', Input_Argument::OPTIONAL, 'name of new actor class');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $this->add_styles($output);
        $suite = ucfirst((string) $input->get_argument('suite'));
        $config = $this->get_global_config();
        $actor = $input->get_argument('actor') ?: $suite . $config['actor_suffix'];
        if ($this->contains_invalid_characters($suite)) {
            $output->writeln("<error>Suite name '{$suite}' contains invalid characters. ([A-Za-z0-9_]).</error>");
            return Command::FAILURE;
        }
        $dir = Configuration::tests_dir();
        if (file_exists($dir . $suite . '.suite.yml')) {
            throw new Exception("Suite configuration file '{$suite}.suite.yml' already exists.");
        }
        $this->create_directory_for($dir . $suite);
        if ($config['settings']['bootstrap']) {
            $this->create_file($dir . $suite . DIRECTORY_SEPARATOR . $config['settings']['bootstrap'], "<?php\n", true);
        }
        $yaml_suite_config_template = <<<EOF
        actor: {{actor}}
        suite_namespace: {{suite_namespace}}
        modules:
            # enable helpers as array
            enabled: []
        EOF;
        $yaml_suite_config = (new Template($yaml_suite_config_template))->place('actor', $actor)->place('suite_namespace', $config['namespace'] . '\\' . $suite)->produce();
        $this->create_file($dir . $suite . '.suite.yml', $yaml_suite_config);
        Configuration::append(Yaml::parse($yaml_suite_config));
        $actor_generator = new Actor_Generator(Configuration::config());
        $content = $actor_generator->produce();
        $file = $this->create_directory_for(Configuration::support_dir(), $actor) . $this->get_short_class_name($actor) . '.php';
        $this->create_file($file, $content);
        $output->writeln("Actor <info>{$actor}</info> was created in {$file}");
        $output->writeln("Suite config <info>{$suite}.suite.yml</info> was created.");
        $output->writeln(' ');
        $output->writeln('Next steps:');
        $output->writeln("1. Edit <bold>{$suite}.suite.yml</bold> to enable modules for this suite");
        $output->writeln('2. Create first test with <bold>generate:cest testName</bold> ( or test|cept) command');
        $output->writeln("3. Run tests of this suite with <bold>codecept run {$suite}</bold> command");
        $output->writeln("<info>Suite {$suite} generated</info>");
        return Command::SUCCESS;
    }
    private function contains_invalid_characters(string $suite): bool
    {
        return (bool) preg_match('#[^A-Za-z0-9_]#', $suite);
    }
}