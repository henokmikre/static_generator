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
      ->addArgument(
        'block_id',
        InputArgument::OPTIONAL,
        $this->trans('commands.sg.generate-blocks.arguments.path'))
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
    $block_id = $input->getArgument('block_id');

    if (empty($block_id)) {
      if (empty($input->getOption('frequent'))) {
        // Generate all blocks.
        $elapsed_time = $this->staticGenerator->generateBlocks(FALSE);
      }
      else {
        // Generate frequent blocks.
        $elapsed_time = $this->staticGenerator->generateBlocks(TRUE);
      }
      $this->getIo()
        ->info('Generate blocks completed, elapsed time: ' . $elapsed_time . ' seconds.');
      //    $this->getIo()->info($this->trans('commands.sg.generate-blocks.messages.success'));
    } else {
      // Generate single block.
      $this->staticGenerator->generateBlock($block_id);
      $this->getIo()
        ->info('Generate of block ' . $block_id . ' complete.');
    }
  }
}
