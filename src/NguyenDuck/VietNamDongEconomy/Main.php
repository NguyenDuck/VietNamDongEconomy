<?php

declare(strict_types=1);

namespace NguyenDuck\VietNamDongEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\{
	Listener,
	player\PlayerJoinEvent
};
use SQLite3;

class Main extends PluginBase implements Listener
{
	private $database;

	public function onEnable()
	{
		$this->database = new SQLite3($this->getDataFolder()."data.db");
		$this->database->exec("CREATE TABLE IF NOT EXISTS players(username VARCHAR(16) NOT NULL PRIMARY KEY, balance INT NOT NULL DEFAULT 0)");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable()
	{
		$this->database->close();
	}

	public function onPlayerJoin(PlayerJoinEvent $event)
	{
		$this->database->exec("INSERT OR IGNORE INTO 'players' VALUES ('".$event->getPlayer()->getName()."',0)");
	}
}
