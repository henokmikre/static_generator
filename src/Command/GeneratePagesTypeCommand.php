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
class GeneratePagesTypeCommand extends ContainerAwareCommand {

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
      ->setName('sg:generate-pages-type')
      ->setDescription($this->trans('commands.sg.generate-pages-type.description'))
      ->addArgument(
        'type',
        InputArgument::REQUIRED,
        $this->trans('commands.sg.generate-pages-type.arguments.type'))
      ->addArgument(
        'start',
        InputArgument::REQUIRED,
        $this->trans('commands.sg.generate-pages-type.arguments.start'))
      ->addArgument(
        'length',
        InputArgument::REQUIRED,
        $this->trans('commands.sg.generate-pages-type.arguments.length'))
      ->setAliases(['sgpt']);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $type = $input->getArgument('type');
    $start = $input->getArgument('start');
    $length = $input->getArgument('length');

    $elapsed_time = $this->staticGenerator->generateNodes($type, FALSE, $start, $length);
    $this->getIo()
      ->info('Generation of pages for type ' . $type . ' complete, elapsed time: ' . $elapsed_time . ' seconds.');
  }
}
