<?php

namespace SoulWell\ZulfahmiFjr\task;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\item\Item;
use pocketmine\item\VamillaItems;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\ItemIds;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\EnchantmentInstance;

use SoulWell\ZulfahmiFjr\Main;
use skymin\InventoryLib\InvLibManager;
use skymin\InventoryLib\LibInvType;
use skymin\InventoryLib\InvLibAction;
use SoulWell\ZulfahmiFjr\manager\NoteBlockSound;

class RollUpdater extends Task{

    private $pl;
    private $p;
    private $delay;
    private $wellMenu;
    private $note = 12;
    private $low = 0;
    private $prediction = null;

    public function __construct(Main $pl, Player $p, $delay){
     $this->pl = $pl;
     $this->p = $p;
     $this->delay = $delay;
     $playerPos = $p->getPosition();
     $wellMenu = InvLibManager::create(LibInvType::DOUBLE_CHEST(), new Position($playerPos->x, $playerPos->y - 2, $playerPos->z, $playerPos->getWorld()), 'Soul Well');
     $wellMenu->setListener(function(InvLibAction $action):void{
      $action->setCancelled();
     });
     $wellMenu->setCloseListener(\Closure::fromCallable([$this, 'closeInventory']));
     $wellMenu->send($p);
     $this->wellMenu = $wellMenu;
    }

    public function closeInventory(Player $p):void{
     if((!is_null($this->getHandler())) && (!$this->getHandler()->isCancelled())){
      if($this->prediction === null){
       $item = $this->getReward();
       if($item !== null){
        if($p->isOnline()){
         $this->addItemToPlayer($p, $item);
         $p->sendMessage("§l§3»§r §7You have recieved§b ".$item->getName()." §ex ".$item->getCount()."§7!");
        }
       }
      }else if(($item = $this->prediction) instanceof Item){
       if($p->isOnline()){
        $this->addItemToPlayer($p, $item);
        $p->sendMessage("§l§3»§r §7You have recieved §b".$item->getName()."§e x ".$item->getCount()." §7!");
       }
      }
      $this->getHandler()->cancel();
     }
    }

    public function getReward():?Item{
     $p = $this->p;
     $items = $this->pl->wellItems;
     $reward = array_rand($items, 1);
     $reward = $items[$reward];
     if(!isset($reward["id"]) || !isset($reward["meta"]) || !isset($reward["amount"])){
      $this->pl->souls->set(strtolower($p->getName()), $this->pl->souls->get(strtolower($p->getName())) + 10);
      $this->pl->souls->save();
      $p->sendMessage("§l§3»§r §cAn error occurred in the SoulWell system. Your Soul Keys will be returned shortly!");
      return null;
     }
     $item = LegacyStringToItemParser::getInstance()->parse($reward["id"] . ":" . $reward["meta"])->setCount($reward["amount"]);
     if(isset($reward["name"])){
      $item->setCustomName($reward["name"]);
     }
     if(isset($reward["lore"])){
      $item->setLore([$reward["lore"]]);
     }
     if(isset($reward["enchantments"])){
      foreach($reward["enchantments"] as $enchantName => $enchantData){
       $level = $enchantData["level"];
       if(!is_null($enchant = StringToEnchantmentParser::getInstance()->parse($enchantName))){
        $item->addEnchantment(new EnchantmentInstance($enchant, $level));
       }
      }
     }
     return $item;
    }

    public function onRun():void{
     $delay = $this->delay;
     $p = $this->p;
     $wellInventory = $this->wellMenu;
     if(($p instanceof Player) && ($p->isOnline())){
      if($this->low <= 0){
       if($this->delay <= 13){
        if($this->delay > 0){
         $this->low += 13 / $this->delay;
        }else{
         $this->low = 0;
        }
       }
       $this->delay--;
       if($delay >= 0){
        $i = 0;
        while($i < 54){
         if($i !== 4 && $i !== 13 && $i !== 22 && $i !== 31 && $i !== 40 && $i !== 49 && $i !== 30 && $i !== 32){
          $wellInventory->setItem($i, VanillaBlocks::STAINED_GLASS_PANE, mt_rand(0, 15)));
         }
         $i++;
        }
        $wellInventory->setItem(30, VanillaItems::END_ROD));
        $wellInventory->setItem(32, VanillaItems::END_ROD, 0));
        if($this->note === 11 || $this->note === 12){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(5));
         if($this->note === 11){
          $this->note = 2;
         }else if($this->note === 12){
          $this->note = 0;
         }
        }else if($this->note === 0){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(2));
         $this->note = 11;
        }else if($this->note === 2){
         $p->getWorld()->addSound($p->getPosition(), new NoteBlockSound(9));
         $this->note = 12;
        }
        $item = $this->getReward();
        if($item !== null){
         $wellInventory->setItem(49, $wellInventory->getItem(40));
         $wellInventory->setItem(40, $wellInventory->getItem(31));
         $wellInventory->setItem(31, $wellInventory->getItem(22));
         $wellInventory->setItem(22, $wellInventory->getItem(13));
         $wellInventory->setItem(13, $wellInventory->getItem(4));
         $wellInventory->setItem(4, $item);
         if($delay === 3){
          $this->prediction = $item;
         }
        }
       }
      }else{
       $this->low--;
      }
      if($delay === -1){
       $wellInventory->setItem(4, VanillaBlocks::AIR, 0));
       $wellInventory->setItem(13, VanillaBlocks::AIR, 0));
       $wellInventory->setItem(22, VanillaBlocks::AIR, 0));
       $wellInventory->setItem(40, VanillaBlocks::AIR, 0));
       $wellInventory->setItem(49, VanillaBlocks::AIR, 0));
      }
      if($delay === -15){
       $item = $wellInventory->getItem(31);
       $this->addItemToPlayer($p, $item);
       $p->sendMessage("§l§3»§r §7You have recieved §b".$item->getName()." §ex ".$item->getCount()."§7!");
       $this->getHandler()->cancel();
       $wellInventory->close($p);
      }
     }
    }

    private function addItemToPlayer(Player $p, Item $item):void{
     $p->getInventory()->addItem($item);
    }
    
}
