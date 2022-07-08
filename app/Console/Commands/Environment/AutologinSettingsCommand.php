<?php


namespace Pterodactyl\Console\Commands\Environment;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Pterodactyl\Traits\Commands\EnvironmentWriterTrait;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class AutologinSettingsCommand extends Command {

    use EnvironmentWriterTrait;

    /**
     * @var \Illuminate\Contracts\Console\Kernel
     */
    protected $console;

    /**
     * @var \Illuminate\Contracts\Config\Respository
     */
    protected $config;

    /**
     * @var string
     */
    protected $description = 'Set or update thr autologin configuration for the Panel.';

    /**
     * @var string
     */
    protected $signature = 'p:environment:autologin
                            {--url= : URL of your dashboard.}
                            {--callback= : Callback path for autologin.}
                            {--key= : API Key for your dashboard.}';
    
    /**
     * @var array
     */
    protected $variables = [];

    /**
     * AutologinSettings constructor
     */
    public function __construct(ConfigRepository $config, Kernel $console) {

        parent::__construct();

        $this->console = $console;
        $this->config = $config;
    }

    public function handle() {

        $this->variables['AUTOLOGIN_URL'] = $this->option('url') ?? $this->ask(
            'Autologin URL (e.g. https://dash.example.com) (no trailing slash)',
            config('autologin.url')
        );

        $this->variables['AUTOLOGIN_CALLBACK'] = $this->option('url') ?? $this->ask(
            'Autologin Callback (e.g. /auth/autologin)',
            config('autologin.callback', '/auth/autologin')
        );

        $askForKey = true;

        if(!empty(config('autologin.key')) && $this->input->isInteractive()) {
            $this->variables['AUTOLOGIN_KEY'] = config('autologin.key');
            $askForKey = $this->confirm('It seems like you already provided a key, do you wanna change it?');
        }

        if($askForKey) {
            $this->variables['AUTOLOGIN_KEY'] = $this->option('key') ?? $this->secret('API Key');
        }

        $this->writeToEnviornment($this->variables);
        $this->info($this->console->output());

        return 0;
    }
}