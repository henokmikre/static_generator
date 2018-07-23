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
 * Class GenerateBlocksCommand.
 *
 * @DrupalCommand (
 *     extension="static_generator",
 *     extensionType="module"
 * )
 */
class GenerateBlocksCommand extends ContainerAwareCommand {

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
      ->setName('sg:generate-blocks')
      ->setDescription($this->trans('commands.sg.generate-blocks.description'))
      ->addOption(
        'frequent',
        NULL,
        InputOption::VALUE_NONE,
        $this->trans('commands.sg.generate-blocks.options.frequent')
      )
      ->setAliases(['sgb']);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    if (empty($input->getOption('frequent'))) {
      $elapsed_time = $this->staticGenerator->generateBlocks(FALSE);
    }
    else {
      $elapsed_time = $this->staticGenerator->generateBlocks(TRUE);
    }
    $this->getIo()
      ->info('Generate blocks completed, elapsed time: ' . $elapsed_time . ' seconds.');
    //    $this->getIo()->info($this->trans('commands.sg.generate-blocks.messages.success'));
  }

}
