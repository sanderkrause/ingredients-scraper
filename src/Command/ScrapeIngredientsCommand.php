<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'scrape:ingredients',
    description: 'Add a short description for your command',
)]
class ScrapeIngredientsCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'input',
            'i',
            InputOption::VALUE_REQUIRED,
            'Input JSON file with URLs to crawl',
            'recipes.json'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $input = $input->getOption('input');

        $io->note('Reading input file ' . $input);

        // @todo read file and decode json
        // @todo loop through array of urls and GET each one
        // @todo crawl the response of each request for ingredients

        $io->success('Done.');

        return Command::SUCCESS;
    }
}
