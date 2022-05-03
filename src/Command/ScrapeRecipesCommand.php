<?php

namespace App\Command;

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
    name: 'scrape:recipes',
    description: 'Scrape ah.nl for recipes',
)]
class ScrapeRecipesCommand extends Command
{
    private array $recipeUrls = [];
    private \Symfony\Contracts\HttpClient\HttpClientInterface $client;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->client = HttpClient::createForBaseUri('https://www.ah.nl');
        $pageNo = 0;
        // Initial visit to find out how many pages there are, and fetch recipe URLs
        try {
            $crawler = $this->visitPage($pageNo);
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        // Find link to last page
        $lastPageLink = $crawler->filter('nav[role=navigation]')->children('ol > li > a')->last()->extract(['href']);
        if (empty($lastPageLink)) {
            $io->error('Last page link not found.');
            return Command::FAILURE;
        }
        // Parse link to check for page number
        $queryString = parse_url($lastPageLink[0], PHP_URL_QUERY);
        parse_str($queryString, $queryParameters);
        if (!isset($queryParameters['page'])) {
            $io->error('Last page number not found.');
            return Command::FAILURE;
        }
        $lastPageNo = (int) $queryParameters['page'];

        $progressBar = new ProgressBar($io, $lastPageNo);
        $progressBar->setFormat(ProgressBar::FORMAT_DEBUG);
        while ($pageNo < $lastPageNo) {
            // Sleep for 0.5 seconds, so the devs of ah.nl don't get angry at us
            usleep(500000);
            $pageNo++;
            try {
                $this->visitPage($pageNo);
                $progressBar->advance();
            } catch (\Exception $e) {
                $progressBar->finish();
                $io->error($e->getMessage());
                return Command::FAILURE;
            }
        }
        $progressBar->finish();

        file_put_contents('recipes.json', json_encode($this->recipeUrls, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $io->success('Done.');

        return Command::SUCCESS;
    }

    /**
     * Visit a page (by number) and crawl for recipe URLs
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     */
    private function visitPage(int $pageNo): Crawler
    {
        $request = $this->client->request('GET', '/allerhande/recepten-zoeken', [
            'query' => [
                'page' => $pageNo,
            ],
        ]);

        $status = $request->getStatusCode();
        if ($status !== 200) {
            throw new \Exception("Requested URL {$request->getInfo('url')} resulted in status " . $status);
        }

        $crawler = new Crawler($request->getContent());
        $recipeUrls = $crawler->filter('a[role=link]')->extract(['href']);

        array_push($this->recipeUrls, ...$recipeUrls);

        return $crawler;
    }
}
