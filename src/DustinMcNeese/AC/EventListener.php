<?php

declare(strict_types=0);

namespace DustinMcNeese\AC;

use pocketmine\plugin\PluginBase;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\permission\Permission;
use pocketmine\math\Vector3;
use pocketmine\Player;

use pocketmine\event\entity\Effect;
use pocketmine\event\Cancellable;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\EntityMotionEvent;

use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerDeathEvent;

use DustinMcNeese\AC\AC;

class EventListener implements Listener
{
  public $Main;
  public $Logger;
  public $Server;

  public function __construct(SAC $Main)
  {
    $this->Main   = $Main;
    $this->Logger = $Main->getServer()->getLogger();
    $this->Server = $Main->getServer();
  }

  public function onJoin(PlayerJoinEvent $event) : void
  {
    $player   = $event->getPlayer();
    $hash     = spl_object_hash($player);
    $name     = $player->getName();

    $oldhash  = null;
    $observer = null;
    /*
    $player->setScale(5);
    //For testing purposes
    */

    foreach ($this->Main->PlayerObservers as $key=>$obs)
    {
      if ($obs->PlayerName == $name)
      {
        $oldhash  = $key;
        $observer = $obs;
        $observer->Player = $player;
      }
    }

    if ($oldhash != null)
    {
      unset($this->Main->PlayerObservers[$oldhash]);
      $this->Main->PlayerObservers[$hash] = $observer;
      $this->Main->PlayerObservers[$hash]->PlayerRejoin();
    }
    else
    {
      $observer = new Observer($player, $this->Main);
      $this->Main->PlayerObservers[$hash] = $observer;
      $this->Main->PlayerObservers[$hash]->PlayerJoin();
    }
  }

  public function onQuit(PlayerQuitEvent $event) : void
  {
    $player   = $event->getPlayer();
    $hash     = spl_object_hash($player);

    if (!empty($player) and !empty($hash) and array_key_exists($hash , $this->Main->PlayerObservers))
    {
      $observer = $this->Main->PlayerObservers[$hash];
      if (!empty($observer))
      {
        $observer->PlayerQuit();
      }
      $this->Main->PlayerObservers[$hash]->Player = null;
    }
  }


  public function onMove(PlayerMoveEvent $event) : void
  {
    $player   = $event->getPlayer();
    $hash     = spl_object_hash($player);

    if (array_key_exists($hash , $this->Main->PlayerObservers))
    {
      if($player != null and $this->Main->PlayerObservers[$hash]->Player != null)
      {
        $this->Main->PlayerObservers[$hash]->OnMove($event);
        /*
        //THIS IS IN-DEV AND NOT USEABLE
        $this->Main->PlayerObservers[$hash]->getRealKnockBack($event);
        */
      }
    }
  }

  public function onEntityMotionEvent(EntityMotionEvent $event) : void
  {
    $ThisEntity = $event->getEntity();
    $hash     = spl_object_hash($event->getEntity());
    if($ThisEntity instanceof Player)
    {
      if (array_key_exists($hash , $this->Main->PlayerObservers))
      {
        if($ThisEntity != null and $this->Main->PlayerObservers[$hash]->Player != null)
        {
          $this->Main->PlayerObservers[$hash]->OnMotion($event);
        }
      }
    }
  }

  public function onEntityRegainHealthEvent(EntityRegainHealthEvent $event) : void
  {
    if ($event->getRegainReason() != EntityDamageEvent::CAUSE_MAGIC and $event->getRegainReason() != EntityDamageEvent::CAUSE_CUSTOM)
    {
      $hash = spl_object_hash($event->getEntity());
      $ThisEntity = $event->getEntity();
      if (array_key_exists($hash , $this->Main->PlayerObservers))
      {
        if(($ThisEntity instanceof Player) and ($ThisEntity != null) and ($this->Main->PlayerObservers[$hash]->Player != null))
        {
          $this->Main->PlayerObservers[$hash]->PlayerRegainHealth($event);
        }
      }
    }
  }

  public function onDamage(EntityDamageEvent $event) : void
  {
    $evname = $event->getEventName();
    $ThisEntity = $event->getEntity();
    $hash = spl_object_hash($ThisEntity);
    if (array_key_exists($hash , $this->Main->PlayerObservers))
    {
      if(($ThisEntity instanceof Player) and ($ThisEntity != null) and ($this->Main->PlayerObservers[$hash]->Player != null))
      {
        $this->Main->PlayerObservers[$hash]->PlayerWasDamaged($event);
      }
    }
    if ($event instanceof EntityDamageByEntityEvent)
    {
      $ThisDamager = $event->getDamager();
      $hash = spl_object_hash($ThisDamager);
      if (array_key_exists($hash , $this->Main->PlayerObservers))
      {
        if(($ThisDamager instanceof Player) and ($ThisDamager != null) and ($this->Main->PlayerObservers[$hash]->Player != null))
        {
          if ($event->getCause() == EntityDamageEvent::CAUSE_ENTITY_ATTACK)
          {
            $this->Main->PlayerObservers[$hash]->PlayerHasDamaged($event);
          }
        }
      }
    }
  }

  public function onEntityShootBowEvent(EntityShootBowEvent $event) : void
  {
    $ThisEntity = $event->getEntity();
    $hash = spl_object_hash($ThisEntity);
    if (array_key_exists($hash , $this->Main->PlayerObservers))
    {
      if(($ThisEntity instanceof Player) and ($ThisEntity != null) and ($this->Main->PlayerObservers[$hash]->Player != null))
      {
        $this->Main->PlayerObservers[$hash]->PlayerShotArrow($event);
      }
    }
  }

  public function onPlayerDeathEvent(PlayerDeathEvent $event) : void
  {
    $player   = $event->getPlayer();
    $hash     = spl_object_hash($player);
    if (array_key_exists($hash , $this->Main->PlayerObservers))
    {
      if($player != null and $this->Main->PlayerObservers[$hash]->Player != null)
      {
        $this->Main->PlayerObservers[$hash]->onDeath($event);
      }
    }
  }

  public function onPlayerRespawnEvent(PlayerRespawnEvent $event) : void
  {
    $player   = $event->getPlayer();
    $hash     = spl_object_hash($player);
    if (array_key_exists($hash , $this->Main->PlayerObservers))
    {
      if($player != null and $this->Main->PlayerObservers[$hash]->Player != null)
      {
        $this->Main->PlayerObservers[$hash]->onRespawn($event);
      }
    }
  }

  //public function onEntityLevelChangeEvent(EntityLevelChangeEvent $event)
  //{
  //  $hash = spl_object_hash($event->getEntity());
  //  if (array_key_exists($hash , $this->Main->PlayerObservers))
  //  {
  //    $this->Main->PlayerObservers[$hash]->onWorldChange($event);
  //  }
  //}

  public function onEntityTeleportEvent(EntityTeleportEvent $event) : void
  {
    $ThisEntity = $event->getEntity();
    $hash = spl_object_hash($event->getEntity());
    if($ThisEntity instanceof Player)
    {
      if (array_key_exists($hash , $this->Main->PlayerObservers))
      {
        if($ThisEntity != null and $this->Main->PlayerObservers[$hash]->Player != null)
        {
          $this->Main->PlayerObservers[$hash]->onTeleport($event);
        }
      }
    }
  }
}