<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\commands\subcommands;

use ColinHDev\CPlot\commands\Subcommand;
use ColinHDev\CPlot\player\PlayerData;
use ColinHDev\CPlot\plots\flags\Flag;
use ColinHDev\CPlot\plots\flags\InternalFlag;
use ColinHDev\CPlot\plots\Plot;
use ColinHDev\CPlot\provider\DataProvider;
use ColinHDev\CPlot\provider\LanguageManager;
use ColinHDev\CPlot\worlds\WorldSettings;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use function array_filter;

class InfoSubcommand extends Subcommand {

    public function execute(CommandSender $sender, array $args) : \Generator {
        if (!$sender instanceof Player) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "info.senderNotOnline"]);
            return;
        }

        if (!((yield DataProvider::getInstance()->awaitWorld($sender->getWorld()->getFolderName())) instanceof WorldSettings)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "info.noPlotWorld"]);
            return;
        }

        $plot = yield Plot::awaitFromPosition($sender->getPosition());
        if (!($plot instanceof Plot)) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "info.noPlot"]);
            return;
        }

        yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["prefix", "info.plot" => [$plot->getWorldName(), $plot->getX(), $plot->getZ()]]);

        $plotOwnerData = [];
        foreach ($plot->getPlotOwners() as $plotOwner) {
            $playerData = $plotOwner->getPlayerData();
            /** @phpstan-var string $addTime */
            $addTime = yield from LanguageManager::getInstance()->getProvider()->awaitTranslationForCommandSender(
                $sender,
                ["info.owners.time.format" => explode(".", date("d.m.Y.H.i.s", $plotOwner->getAddTime()))]
            );
            $plotOwnerData[] = yield from LanguageManager::getInstance()->getProvider()->awaitTranslationForCommandSender(
                $sender,
                ["info.owners.list" => [
                    $playerData->getPlayerName() ?? "Error: " . ($playerData->getPlayerXUID() ?? $playerData->getPlayerUUID() ?? $playerData->getPlayerID()),
                    $addTime
                ]]
            );
        }
        if (count($plotOwnerData) === 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.owners.none"]);
        } else {
            /** @phpstan-var string $separator */
            $separator = yield from LanguageManager::getInstance()->getProvider()->awaitTranslationForCommandSender($sender, "info.owners.list.separator");
            $list = implode($separator, $plotOwnerData);
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage(
                $sender,
                ["info.owners" => $list]
            );
        }

        if ($plot->getAlias() !== null) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.plotAlias" => $plot->getAlias()]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.plotAlias.none"]);
        }

        $mergedPlotsCount = count($plot->getMergePlots());
        if ($mergedPlotsCount > 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.merges" => $mergedPlotsCount]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.merges.none"]);
        }

        $trustedCount = count($plot->getPlotTrusted());
        if ($trustedCount > 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.trusted" => $trustedCount]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.trusted.none"]);
        }
        $helpersCount = count($plot->getPlotHelpers());
        if ($helpersCount > 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.helpers" => $helpersCount]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.helpers.none"]);
        }
        $deniedCount = count($plot->getPlotDenied());
        if ($deniedCount > 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.denied" => $deniedCount]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.denied.none"]);
        }

        $flagsCount = count(
            array_filter(
                $plot->getFlags(),
                static function(Flag $flag) : bool {
                    return !($flag instanceof InternalFlag);
                }
            )
        );
        if ($flagsCount > 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.flags" => $flagsCount]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.flags.none"]);
        }

        $ratesCount = count($plot->getPlotRates());
        if ($ratesCount > 0) {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.rates" => $ratesCount]);
        } else {
            yield from LanguageManager::getInstance()->getProvider()->awaitMessageSendage($sender, ["info.rates.none"]);
        }
    }
}