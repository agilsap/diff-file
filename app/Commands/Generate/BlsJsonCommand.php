<?php

namespace App\Commands\Generate;

use App\Services\RepoDpService;
use App\Services\RepoMasterService;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\File;


class BlsJsonCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'generate:blsjson_app
                            {branch : branch name repo (required)}
                            {--push : create dp folder and push to repo DP_APP_DB (optional)}
                            {--push-only : dont create dp folder, just push to repo DP_APP_DB (optional)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Generate diff files and create BLS_APP repo.';

    /**
     * Https url repo master, dp_app_db and error message.
     *
     * @var string
     */

    protected $repoName = "blsjson_app";
    protected $httpsMasterRepo = "https://bli-org01@dev.azure.com/bli-org01/BLS/_git/BLS";
    protected $httpsDpAppDb = "https://bli-org01@dev.azure.com/bli-org01/DP_APP_DB/_git/BLSJSON_APP";
    protected $errorMessage = '';

    /**
     * Execute the console command.
     *
     * @return mixed
     */

    private RepoMasterService $repoMasterService;
    private RepoDpService $repoDpService;

    public function __construct(RepoMasterService $repoMasterService, RepoDpService $repoDpService)
    {
        $this->repoMasterService = $repoMasterService;
        $this->repoMasterService->setRepo($this->repoName);
        $this->repoDpService = $repoDpService;
        $this->repoDpService->setRepo($this->repoName);

        parent::__construct();
    }

    public function handle()
    {
        $branchName = $this->argument('branch');
        $isPush = $this->option('push');
        $isPushOnly = $this->option('push-only');

        $initRepoMaster = $this->task("Initialize Repo Master", function () use ($branchName) {
            try {
                $bar = $this->output->createProgressBar(100);
                $bar->setFormatDefinition('custom', '[%bar%] %message% - %percent:3s%%');
                $bar->setFormat('custom');
                $bar->setProgressCharacter('🚀');
                $bar->setBarCharacter('=');
                $bar->setMessage('Clone Repo...');
                $bar->start();
                    
                    $this->repoMasterService->clone($this->httpsMasterRepo);
                    $bar->advance(25);
        
                    $bar->setMessage('Checkout to branch...');
                    $this->repoMasterService->checkout($branchName);
                    $bar->advance(25);

                    $bar->setMessage('Checking request merger...');
                    $diffMergeArray = $this->repoMasterService->getDiffMerge($branchName, 'origin/master');
                    if (!empty($diffMergeArray)) {
                        $bar->clear();
                        $this->errorMessage = '<info>Your branch have difference with lasted repo master, please merge!<info>';
                        return false;
                    }
                    $bar->advance(25);
                    
                    $bar->setMessage('Generate diff file...');
                    $this->repoMasterService->generateDiffTxt($branchName, 'origin/master');
                    $bar->advance(25);
    
                    $bar->setMessage('Finish');
                $bar->finish();
                return true;
            } catch (\Throwable $th) {
                $this->errorMessage = $th;
                return false;
            }
        }, '');

        if (!$initRepoMaster) {
            $this->output->writeln($this->errorMessage);
            exit;
        }

        $initRepoDP =  $this->task("Initialize Repo DP", function () use ($branchName) {
            try {
                $bar = $this->output->createProgressBar(100);
                $bar->setFormatDefinition('custom', '[%bar%] %message% - %percent:3s%%');
                $bar->setFormat('custom');
                $bar->setProgressCharacter('🚀');
                $bar->setBarCharacter('=');
                $bar->setMessage('Clone Repo...');
                $bar->start();
                    $this->repoDpService->clone($this->httpsDpAppDb);
                    $bar->advance(40);
        
                    $bar->setMessage('Checkout to branch...');
                    $this->repoDpService->checkout($branchName);
                    $bar->advance(30);
                    
                    $bar->setMessage('Rename branch...');
                    $this->repoDpService->rename('XXXFPPTAHUN', $branchName);
                    $bar->advance(30);
        
                    $bar->setMessage('Finish');
                $bar->finish();
                return true;
            } catch (\Throwable $th) {
                $this->errorMessage = $th;
                return false;
            }
        }, '');

        if (!$initRepoDP) {
            $this->output->writeln($this->errorMessage);
            exit;
        }

        $generateDiffFiles = $this->task("Generate Diff Files", function () use ($branchName) {
            try {
                $bar = $this->output->createProgressBar();
                $bar->setFormatDefinition('custom', '[%bar%] %message% - %percent:3s%%');
                $bar->setFormat('custom');
                $bar->setProgressCharacter('🚀');
                $bar->setBarCharacter('=');
                $bar->setMessage('Copying Files...');
                $bar->start();
                    $filesDiffMaster = $this->repoMasterService->getDiffData();
                    foreach ($filesDiffMaster as $fileMaster) {
                        $this->repoDpService->copyFile(
                            $this->repoMasterService->baseUrlRepo . "/$fileMaster", 
                            "$branchName/Source/bls/$fileMaster"
                        );
                        $bar->advance();
                    }

                    $filesDiffDP = $this->repoDpService->getDiffData($branchName);

                    $filesExclude = array_merge(['Generate ALL'], $filesDiffDP);
                    $bar->clear();
                    $excludeChoice = $this->choice(
                        'This is your updated files in repo DP, is there any file you want to exclude? multiple choice example: 1,2 (if you want generate all insert 0)',
                        $filesExclude,
                        null,
                        null,
                        true
                    );

                    foreach ($excludeChoice as $key => $excludeFile) {
                        $this->repoDpService->restoreFile($branchName, "$excludeFile");
                    }

                    $filesDiffBackup = array_diff($filesDiffMaster, $excludeChoice);

                    $bar->setMessage('Update Backup Data...');
                    $newDataBackup = $this->repoMasterService->getValidBackupData($filesDiffBackup, $branchName);
                    $newFileBackup = implode("\n", $newDataBackup);
                    $this->repoDpService->editFile("$branchName/SourceBackup.txt", $newFileBackup);
                    $bar->advance();

                    $bar->setMessage('Update Backup Views Data...');
                    $newDataBackupViews = $this->repoMasterService->getValidBackupViewData($filesDiffBackup, $branchName);
                    $newFileBackup = implode("\n", $newDataBackupViews);
                    $this->repoDpService->editFile("$branchName/SourceBackupVIEW.txt", $newFileBackup);
                    $bar->advance();

                    $bar->setMessage('Update Version...');
                    $this->repoDpService->updateVersion($branchName, 1);
                    $bar->advance();
                $bar->finish();
                return true;
            } catch (\Throwable $th) {
                $this->errorMessage = $th;
                return false;
            }
        }, '');

        if (!$generateDiffFiles) {
            $this->output->writeln($this->errorMessage);
            exit;
        }

        if ($isPush || $isPushOnly) {
            $pushDPRepo = $this->task("Commit and Push DP", function () use ($branchName) {
                try {
                    $bar = $this->output->createProgressBar();
                    $bar->setFormatDefinition('custom', '[%bar%] %message% - %percent:3s%%');
                    $bar->setFormat('custom');
                    $bar->setProgressCharacter('🚀');
                    $bar->setBarCharacter('=');
                    $bar->setMessage('Commit DP Repo...');
                    $bar->start();
                        $bar->clear();
                        $comment = $this->ask("Insert comment for commit?");
                        $bar->advance();
                        $this->repoDpService->commitAndPush($branchName, $comment);
                    $bar->finish();
    
                    return true;
                } catch (\Throwable $th) {
                    $this->errorMessage = $th;
                    return false;
                }
            }, '');

            if (!$pushDPRepo) {
                $this->output->writeln($this->errorMessage);
                exit;
            }
        }

        if (!$isPushOnly) {
            $copyDPRepo = $this->task("Copy DP Repo", function () use ($branchName) {
                try {
                    $bar = $this->output->createProgressBar();
                    $bar->setFormatDefinition('custom', '[%bar%] %message% - %percent:3s%%');
                    $bar->setFormat('custom');
                    $bar->setProgressCharacter('🚀');
                    $bar->setBarCharacter('=');
                    $bar->setMessage('Copying Files...');
                    $bar->start();
    
                    chdir("/");
                    $bar->clear();
                    $pathDPFolder = $this->ask('Insert path for DP Folder? (please insert full path directory!)');
                    if (!is_dir($pathDPFolder)) {
                        $this->errorMessage = "<info>Incorrect path!<info>";
                        return false;
                    }
                    $bar->advance();
    
                    $uniqueName = $branchName .'_'. date('YmdHis');
                    File::copyDirectory($this->repoDpService->baseUrlRepo, "$pathDPFolder/$this->repoName"."_$uniqueName/");
    
                    $bar->finish();
                    return true;
                } catch (\Throwable $th) {
                    $this->errorMessage = $th;
                    return false;
                }
            }, '');
    
            if (!$copyDPRepo) {
                $this->output->writeln($this->errorMessage);
                exit;
            }
        }
        
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
