<?php

namespace Drupal\static_generator\Command;

use Drupal\static_generator\StaticGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;


/**
 * Class GeneratePageCommand.
 *
 * @DrupalCommand (
 *     extension="static_generator",
 *     extensionType="module"
 * )
 */
class GeneratePagesCommand extends ContainerAwareCommand {

  /**
   * The Static Generator service.
   *
   * @var \Drupal\static_generator\StaticGenerator
   */
  protected $staticGenerator;

  /**
   * GenPageCommand constructor.
   *
   * @param \Drupal\static_generator\StaticGenerator $static_generator
   */
  public function __construct(StaticGenerator $static_generator) {
    $this->staticGenerator = $static_generator;
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  protected function configure() {
    $this
      ->setName('sg:generate-pages')
      ->setDescription($this->trans('commands.sg.generate-pages.description'))
      ->addArgument(
        'path',
        InputArgument::OPTIONAL,
        $this->trans('commands.sg.generate-pages.arguments.path'))
      ->addOption(
        'quite',
        NULL,
        InputOption::VALUE_NONE,
        $this->trans('commands.sg.generate-all.options.quite'))
      ->setAliases(['sgp']);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $path = $input->getArgument('path');
    if (empty($path)) {
      if (empty($input->getOption('quite'))) {
        $answer = $this->getIo()
          ->ask('Delete and re-generate all pages (yes/no)? ');
        if (strtolower($answer) == 'yes') {
          $elapsed_time = $this->staticGenerator->generatePages();
          $this->getIo()
            ->info('Generate pages completed, elapsed time: ' . $elapsed_time . ' seconds.');
        }
      }
      else {
        $elapsed_time = $this->staticGenerator->generatePages();
        $this->getIo()
          ->info('Generate pages completed, elapsed time: ' . $elapsed_time . ' seconds.');
      }
    }
    else {
      $this->staticGenerator->generatePage($path);
      $this->getIo()
        ->info('Generation of page for path ' . $path . ' complete.');
      //    $this->getIo()->info($this->trans('commands.sg.generate-page.messages.success'));
    }
  }

}
