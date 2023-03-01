<?php

namespace Winter\Translate\Console;

use Illuminate\Console\Command;
use Winter\Translate\Classes\ThemeScanner;
use Winter\Translate\Models\Message;

class ScanCommand extends Command
{
    /**
     * @var string|null The default command name for lazy loading.
     */
    protected static $defaultName = 'translate:scan';

    /**
     * @var string The name and signature of this command.
     */
    protected $signature = 'translate:scan
        {--p|purge : Purge existing messages before scanning.}';

    /**
     * @var string The console command description.
     */
    protected $description = 'Scan theme localization files for new messages.';

    public function handle()
    {
        if ($this->option('purge')) {
            $this->output->writeln('Purging messages...');
            Message::truncate();
        }

        ThemeScanner::scan();
        $this->output->success('Messages scanned successfully.');
        $this->output->note('You may need to run cache:clear for updated messages to take effect.');
    }
}
