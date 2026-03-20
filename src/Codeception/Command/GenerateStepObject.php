<?php

declare (strict_types=1);
namespace Codeception\Command;

use Codeception\Configuration;
use Codeception\Lib\Generator\Step_Object as StepObjectGenerator;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Question_Helper;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Question\Question;
use function ucfirst;
/**
 * Generates StepObject class. You will be asked for steps you want to implement.
 *
 * * `codecept g:stepobject Acceptance AdminSteps`
 * * `codecept g:stepobject Acceptance UserSteps --silent` - skip action questions
 *
 */
#[As_Command(name: 'generate:stepobject', description: 'Generates empty StepObject class')]
class Generate_Step_Object extends Command
{
    use Shared\File_System_Trait;
    use Shared\Config_Trait;
    protected function configure(): void
    {
        $this->add_argument('suite', Input_Argument::REQUIRED, 'Suite for StepObject')->add_argument('step', Input_Argument::REQUIRED, 'StepObject name')->add_option('silent', '', Input_Option::VALUE_NONE, 'Skip verification question');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $suite = (string) $input->get_argument('suite');
        $step = $input->get_argument('step');
        $config = $this->get_suite_config($suite);
        $class = $this->get_short_class_name($step);
        $path = $this->create_directory_for(Configuration::support_dir() . 'Step' . DIRECTORY_SEPARATOR . ucfirst($suite), $step);
        /** @var QuestionHelper $dialog */
        $dialog = $this->get_helper('question');
        $filename = $path . $class . '.php';
        $step_object = new Step_Object_Generator($config, ucfirst($suite) . '\\' . $step);
        if (!$input->get_option('silent')) {
            do {
                $question = new Question('Add action to StepObject class (ENTER to exit): ');
                $action = $dialog->ask($input, $output, $question);
                if ($action) {
                    $step_object->create_action($action);
                }
            } while ($action);
        }
        $res = $this->create_file($filename, $step_object->produce());
        if (!$res) {
            $output->writeln("<error>StepObject {$filename} already exists</error>");
            return Command::FAILURE;
        }
        $output->writeln("<info>StepObject was created in {$filename}</info>");
        return Command::SUCCESS;
    }
}