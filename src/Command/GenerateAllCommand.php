<?php

namespace Drupal\static_generator\Command;

use Drupal\static_generator\StaticGenerator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;


/**
 * Class GenerateAllCommand.
 *
 * @DrupalCommand (
 *     extension="static_generator",
 *     extensionType="module"
 * )
 */
class GenerateAllCommand extends ContainerAwareCommand {

  /**
   * The Static Generator service.
   *
   * @var \Drupal\static_generator\StaticGenerator
   */
  protected $staticGenerator;

  /**
   * GenCommand constructor.
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
      ->setName('sg:generate-all')
      ->setDescription($this->trans('commands.sg.generate-pages.description'))
      ->setAliases(['ga']);
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $start_time = time();
    $this->staticGenerator->generateAll();
    $end_time = time();
    $elapsed_time = $end_time - $start_time;
    $this->getIo()->info('Elapsed time: ' . $elapsed_time . ' seconds.');
    //$this->getIo()->info($this->trans('commands.sg.generate-pages.messages.success'));
    $this->getIo()->info('Full site static generation complete.');
  }

}
