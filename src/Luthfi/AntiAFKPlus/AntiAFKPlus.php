<?php

namespace Luthfi\AntiAFKPlus;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\scheduler\Task;
use LootSpace369\lsplaceholderapi\PlaceHolderAPI;

class AntiAFKPlus extends PluginBase implements Listener {

    private array $afkTimes = [];
    protected ?PlaceholderAPI $placeholderAPI = null;
    private const CONFIG_VERSION = "1.0.0";

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->checkConfigVersion();
        $this->placeholderAPI = $this->getServer()->getPluginManager()->getPlugin("LSPlaceholderAPI");
        if (!$this->placeholderAPI instanceof PlaceholderAPI) {
            $this->getLogger()->error("LSPlaceholderAPI not found! The plugin will not function correctly.");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }
        $this->startAFKCheckTask();
    }

    private function checkConfigVersion(): void {
        $configVersion = $this->getConfig()->get("config_version", "0.0.0");
        if (version_compare($configVersion, self::CONFIG_VERSION, '<')) {
            $this->getLogger()->warning("Your config.yml is outdated. Please update it with the latest configuration options.");
        }
        $this->getConfig()->set("config_version", self::CONFIG_VERSION);
        $this->saveConfig();
    }

    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $this->afkTimes[$player->getName()] = time();
    }

    private function startAFKCheckTask(): void {
        $timeout = $this->getConfig()->get("afk_timeout", 5) * 60;
        $kickMessage = $this->getConfig()->get("kick_message", "You were kicked for being AFK!");

        $this->getScheduler()->scheduleRepeatingTask(new class($this, $timeout, $kickMessage) extends Task {
            private AntiAFKPlus $plugin;
            private int $timeout;
            private string $kickMessage;

            public function __construct(AntiAFKPlus $plugin, int $timeout, string $kickMessage) {
                $this->plugin = $plugin;
                $this->timeout = $timeout;
                $this->kickMessage = $kickMessage;
            }

            public function onRun(): void {
                $currentTime = time();
                $timezone = date_default_timezone_get(); 
                foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
                    $lastMoveTime = $this->plugin->afkTimes[$player->getName()] ?? $currentTime;
                    if (($currentTime - $lastMoveTime) >= $this->timeout) {
                        $placeholders = [
                            "{time}" => $timezone,
                            "{name}" => $player->getName()
                        ];
                        $message = $this->plugin->placeholderAPI->applyPlaceholders($this->kickMessage, $placeholders);
                        $player->kick($message, false);
                    }
                }
            }
        }, 20 * 60);
    }
}
