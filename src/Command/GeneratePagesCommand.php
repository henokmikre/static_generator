<?php

namespace Drupal\static_generator\Command;

use Drupal\static_generator\StaticGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
        $this->trans('commands.sg.generate-page.arguments.path')
      )
      ->setAliases(['gp']);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $path = $input->getArgument('path');
    if (empty($path)) {
      $elapsed_time = $this->staticGenerator->generatePages();
    }
    else {
      $elapsed_time = $this->staticGenerator->generatePage($path);
      $this->staticGenerator->generateBlocks();
    }
    $this->getIo()->info('Elapsed time: ' . $elapsed_time . ' seconds.');
    $this->getIo()
      ->info($this->trans('commands.sg.generate-page.messages.success'));
  }

}
