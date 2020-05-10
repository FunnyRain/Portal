<?php

namespace Vyxel;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class main_portal extends PluginBase implements Listener {

    public $config, $messages, $temp_datas = [], $preload = [];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        // yaml гавно , любите json! а еще извиняюсь перед ржецом за такой код
        $this->messages = new Config($this->getDataFolder()."messages.yml", Config::YAML, [
            "maxPortalPlayer" => 1,
            "blockPortal" => 120,
            "blockActivatePortal" => 351,
            "messagePlacePortal" => "Вы можете установить портал, указав 2 точки с помощью красителя",
            "messageBreakPortal" => "Вы сломали портал, он больше не активен",
            "messageActivePortal" => "Вы указали %n-ю точку",
            "messageSuccess" => "Портал успешно создан.",
            "messageFailActivatePortal" => "Вы не можете создать больше %n-го портала",
            "messageTeleport" => "Телепортируемся.... ."
        ]);
        $this->config = new Config($this->getDataFolder()."portals.json", Config::JSON);
        $this->getLogger()->info("Загрузка координат..");
        foreach ($this->config->getAll() as $item => $value) {
            $this->preload[$this->config->getNested("{$item}.pos1")] = $this->config->getNested("{$item}.pos2");
            $this->preload[$this->config->getNested("{$item}.pos2")] = $this->config->getNested("{$item}.pos1");
        }
    }

    public function placePortal(BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock()->getId();
        if ($block == $this->messages->get("blockPortal")) {
            $player->sendMessage($this->messages->get("messagePlacePortal"));
        }
    }

    public function removePortal(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock()->getId();
        if ($block == $this->messages->get("blockPortal")) {
            $player->sendMessage($this->messages->get("messageBreakPortal"));
        }
    }

    public function movePortal(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        $x = floor($player->getX());
        $y = ceil($player->getY());
        $z = floor($player->getZ());
        $xuy = "{$x}xuy{$y}xuy{$z}";
        if (in_array($xuy, $this->preload) and isset($this->preload[$xuy])) {
            $decode_xuy = explode("xuy", $this->preload[$xuy]);
            if ($player->getLevel()->getBlock(new Vector3($decode_xuy[0], $decode_xuy[1]-1, $decode_xuy[2]))->getId() == $this->messages->get("blockPortal")) {
                $player->teleport(new Position($decode_xuy[0], $decode_xuy[1]+1, $decode_xuy[2]+1, $this->getServer()->getDefaultLevel()));
                $player->sendMessage($this->messages->get("messageTeleport"));
            } else {
                unset($this->preload[$xuy]);
            }
        }
    }

    public function activatePortal(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        $item = $player->getInventory()->getItemInHand()->getId();
        $block = $event->getBlock()->getId();
        $blockXYZ = $event->getBlock();
        if (!isset($this->temp_datas[$name]))
            $this->temp_datas[$name] = [ "pos1" => null, "pos2" => null ];
        if ($item == $this->messages->get("blockActivatePortal")) {
            if ($block == $this->messages->get("blockPortal")) {
                if ($this->config->exists($name)) {
                    $player->sendMessage(str_replace("%n", $this->messages->get("maxPortalPlayer"), $this->messages->get("messageFailActivatePortal")));
                    $event->setCancelled(true);
                } else {
                    if (is_null($this->temp_datas[$name]["pos1"])) {
                        $x = $blockXYZ->getX();
                        $y = $blockXYZ->getY() + 1;
                        $z = $blockXYZ->getZ();
                        $this->temp_datas[$name]["pos1"] = "{$x}xuy{$y}xuy{$z}";
                        $player->sendMessage(str_replace("%n", 1, $this->messages->get("messageActivePortal")));
                    } elseif (!is_null($this->temp_datas[$name]["pos1"]) and is_null($this->temp_datas[$name]["pos2"])) {
                        $x = $blockXYZ->getX();
                        $y = $blockXYZ->getY() + 1;
                        $z = $blockXYZ->getZ();
                        $this->temp_datas[$name]["pos2"] = "{$x}xuy{$y}xuy{$z}";
                        $player->sendMessage(str_replace("%n", 2, $this->messages->get("messageActivePortal")));
                        // set config
                        $this->config->setNested("{$name}.pos1", $this->temp_datas[$name]["pos1"]);
                        $this->config->setNested("{$name}.pos2", $this->temp_datas[$name]["pos2"]);
                        $this->config->setNested("{$name}.owner", $name);
                        $this->config->save();
                        $this->preload[$this->config->getNested("{$name}.pos1")] = $this->config->getNested("{$name}.pos2");
                        $this->preload[$this->config->getNested("{$name}.pos2")] = $this->config->getNested("{$name}.pos1");
                        $player->sendMessage($this->messages->get("messageSuccess"));
                    } else {
                        $player->sendMessage(str_replace("%n", $this->messages->get("maxPortalPlayer"), $this->messages->get("messageFailActivatePortal")));
                    }

                }
            }
        }
    }

    public function onDisable() {
        parent::onDisable(); // TODO: Change the autogenerated stub
        $this->getLogger()->info("Выгрузка координат..");
        unset($this->preload);
        $this->config->save();
    }
}
