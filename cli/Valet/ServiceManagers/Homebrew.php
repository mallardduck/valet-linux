<?php

namespace Valet\ServiceManagers;

use DomainException;
use Valet\CommandLine;
use Valet\Contracts\ServiceManager;

class Homebrew implements ServiceManager
{
    public $cli;

    /**
     * Create a new Brew instance.
     *
     * @param CommandLine $cli CommandLine object
     */
    public function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    /**
     * Start the given services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function start($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            valet_info("Starting $service...");
            $this->cli->quietlyAsUser('brew services start ' . $this->getRealService($service));
        }
    }

    /**
     * Stop the given services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function stop($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            valet_info("Stopping $service...");
            $this->cli->quietlyAsUser('brew services stop ' . $this->getRealService($service));
            $this->cli->quietlyAsUser('sudo brew services stop ' . $this->getRealService($service));
        }
    }

    /**
     * Restart the given services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function restart($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            valet_info("Restarting $service...");
            $this->cli->quietlyAsUser('brew services stop ' . $this->getRealService($service));
            $this->cli->quietly('sudo brew services stop ' . $this->getRealService($service));
            $this->cli->quietly('sudo brew services start ' . $this->getRealService($service));
        }
    }

    /**
     * Status of the given services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function printStatus($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            $status = $this->cli->runAsUser('brew services info ' . $this->getRealService($service) . ' --json');
            $statusObject = json_decode($status)[0];
            $running = $statusObject['running'];

            if ($running) {
                valet_info(ucfirst($service) . ' is running...');
            } else {
                valet_warning(ucfirst($service) . ' is stopped...');
            }
        }
    }

    /**
     * Status of the given services.
     *
     * @param mixed $service Service name
     *
     * @return void
     */
    public function status($service)
    {
        return $this->cli->run('brew services info ' . $this->getRealService($service));
    }

    /**
     * Check if service is disabled.
     *
     * @param mixed $service Service name
     *
     * @return void
     */
    public function disabled($service)
    {
        return false;
        $service = $this->getRealService($service);

        return (strpos(trim($this->cli->run("systemctl is-enabled {$service}")), 'enabled')) === false;
    }

    /**
     * Enable services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function enable($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            try {
                $service = $this->getRealService($service);

                if ($this->disabled($service)) {
                    $this->cli->quietly('sudo systemctl enable ' . $service);
                    valet_info(ucfirst($service) . ' has been enabled');

                    return true;
                }

                valet_info(ucfirst($service) . ' was already enabled');

                return true;
            } catch (DomainException $e) {
                valet_warning(ucfirst($service) . ' unavailable.');

                return false;
            }
        }
    }

    /**
     * Disable services.
     *
     * @param mixed $services Service name
     *
     * @return void
     */
    public function disable($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            try {
                $service = $this->getRealService($service);

                if (!$this->disabled($service)) {
                    $this->cli->quietly('sudo systemctl disable ' . $service);
                    valet_info(ucfirst($service) . ' has been disabled');

                    return true;
                }

                valet_info(ucfirst($service) . ' was already disabled');

                return true;
            } catch (DomainException $e) {
                valet_warning(ucfirst($service) . ' unavailable.');

                return false;
            }
        }
    }

    /**
     * Determine if service manager is available on the system.
     *
     * @return bool
     */
    public function isAvailable()
    {
        try {
            $output = $this->cli->runAsUser(
                'brew services',
                function ($exitCode, $output) {
                    throw new DomainException('Systemd not available');
                }
            );

            return $output != '';
        } catch (DomainException $e) {
            return false;
        }
    }

    /**
     * Determine real service name
     *
     * @param mixed $service Service name
     *
     * @return string
     */
    public function getRealService($service)
    {
        return collect($service)->first(
            function ($service) {
                return strpos($this->cli->run("systemctl status {$service} | grep Loaded"), 'Loaded: loaded') >= 0;
            },
            function () {
                throw new DomainException("Unable to determine service name.");
            }
        );
    }

    /**
     * Install Valet DNS services.
     *
     * @param Filesystem $files Filesystem object
     *
     * @return void
     */
    public function installValetDns($files)
    {
        valet_info("Installing Valet DNS service...");

        $files->put(
            '/etc/systemd/system/valet-dns.service',
            $files->get(__DIR__ . '/../../stubs/init/systemd')
        );

        $this->enable('valet-dns');
    }
}
