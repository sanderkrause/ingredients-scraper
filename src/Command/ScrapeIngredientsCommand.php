<?php

namespace App\Command;

use PhpParser\Node\Expr\New_;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

#[AsCommand(
    name: 'scrape:ingredients',
    description: 'Add a short description for your command',
)]
class ScrapeIngredientsCommand extends Command
{
    private \Symfony\Contracts\HttpClient\HttpClientInterface $client;

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
        $this->client = HttpClient::createForBaseUri('https://www.ah.nl');
        $all_ingredients = [];

        $io->note('Reading input file ' . $input);

        // Read file and decode json
        $recipe_list = json_decode(file_get_contents($input));
        // Loop through array of urls and GET each one
        $progressBar = new ProgressBar($io, count($recipe_list));
        $progressBar->setFormat(ProgressBar::FORMAT_DEBUG);
        foreach ($recipe_list as $recipe) {
//            usleep(200000); // 0.2s
            $request = $this->client->request('GET', $recipe);

            $status = $request->getStatusCode();
            if ($status !== 200) {
                throw new \Exception("Requested URL {$request->getInfo('url')} resulted in status " . $status);
            }

            // Crawl the response of each request for ingredients
            $crawler = new Crawler($request->getContent());
            $filter = $crawler->filter('div[aria-label=ingredienten] > p');
            $i = 0;
            $ingredient_list = [];
            /** @var \DOMNode $node */
            foreach ($filter->getIterator() as $node) {
                if ($i % 2 !== 0) {
                    $ingredient_list[] = $node->nodeValue;
                }
                $i++;
            }
            array_push($all_ingredients, ...$ingredient_list);
            $progressBar->advance();
        }
        $all_ingredients = array_unique($all_ingredients);
        sort($all_ingredients);
        file_put_contents('ingredients.json', json_encode($all_ingredients, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $progressBar->finish();
        $io->success('Done. Scraped '. count($all_ingredients). " ingredients from ".count($recipe_list). " recipes");

        return Command::SUCCESS;
    }
}
