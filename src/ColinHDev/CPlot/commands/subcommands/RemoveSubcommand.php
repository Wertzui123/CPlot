<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\event\PlotPlayerRemoveAsyncEvent;
use ColinHDev\CPlot\player\PlayerData;
use ColinHDev\CPlot\player\settings\implementation\InformRemovedSetting;
use ColinHDev\CPlot\player\settings\Settings;
use ColinHDev\CPlot\plots\lock\PlotLockManager;
use ColinHDev\CPlot\plots\lock\RemovePlotPlayerLockID;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\plots\PlotPlayer;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use poggit\libasynql\SqlError;

class RemoveSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : \Generator {
        if (!$sender instanceof Player) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.senderNotOnline"]);
            return;
        }

        if (count($args) === 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.usage"]);
            return;
        }

        if ($args[0] !== "*") {
            $player = $sender->getServer()->getPlayerByPrefix($args[0]);
            if ($player instanceof Player) {
                $playerName = $player->getName();
            } else {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.playerNotOnline" => $args[0]]);
                $playerName = $args[0];
                $player = yield DataProvider::getInstance()->awaitPlayerDataByName($playerName);
            }
        } else {
            $playerName = "*";
            $player = yield from DataProvider::getInstance()->awaitPlayerDataByXUID("*");
        }
        if (!($player instanceof Player) && !($player instanceof PlayerData)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.playerNotFound" => $playerName]);
            return null;
        }

        if (!((yield DataProvider::getInstance()->awaitWorld($sender->getWorld()->getFolderName())) instanceof WorldSettings)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.noPlotWorld"]);
            return;
        }

        $plot = yield Plot::awaitFromPosition($sender->getPosition());
        if (!($plot instanceof Plot)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.noPlot"]);
            return;
        }

        if (!$plot->hasPlotOwner()) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.noPlotOwner"]);
            return;
        }
        if (!$sender->hasPermission("cplot.admin.remove")) {
            if (!$plot->isPlotOwner($sender)) {
                yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.notPlotOwner"]);
                return;
            }
        }

        $plotPlayer = $plot->getPlotPlayerExact($player);
        if (!($plotPlayer instanceof PlotPlayer) || $plotPlayer->getState() !== PlotPlayer::STATE_HELPER) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.playerNotHelper" => $playerName]);
            return;
        }

        $playerData = $plotPlayer->getPlayerData();
        $lock = new RemovePlotPlayerLockID($playerData->getPlayerID());
        if (!PlotLockManager::getInstance()->lockPlotsSilent($lock, $plot)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.plotLocked"]);
            return;
        }

        /** @phpstan-var PlotPlayerRemoveAsyncEvent $event */
        $event = yield from PlotPlayerRemoveAsyncEvent::create($plot, $plotPlayer, $sender);
        if ($event->isCancelled()) {
            PlotLockManager::getInstance()->unlockPlots($lock, $plot);
            return;
        }

        $plot->removePlotPlayer($plotPlayer);
        try {
            yield from DataProvider::getInstance()->deletePlotPlayer($plot, $playerData->getPlayerID());
        } catch (SqlError $exception) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.saveError" => $exception->getMessage()]);
            return;
        } finally {
            PlotLockManager::getInstance()->unlockPlots($lock, $plot);
        }
        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "remove.success" => $playerName]);

        if ($player instanceof Player && $playerData->getSetting(Settings::INFORM_REMOVED())->equals(InformRemovedSetting::TRUE())) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage(
                $player,
                ["prefix", "remove.success.player" => [$sender->getName(), $plot->getWorldName(), $plot->getX(), $plot->getZ()]]
            );
        }
    }
}