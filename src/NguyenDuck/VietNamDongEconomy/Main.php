<?php

declare(strict_types=1);

namespace NguyenDuck\VietNamDongEconomy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\{
	Listener,
	player\PlayerJoinEvent
};
use pocketmine\Player;
use pocketmine\command\{
	Command,
	CommandSender
};
use libs\jojoe77777\FormAPI\{
	SimpleForm,
	ModalForm,
	CustomForm
};
use SQLite3;
use function count;
use function implode;

class Main extends PluginBase implements Listener
{
	private static $instance;
	private $database;

	public function onEnable()
	{
		self::$instance = $this;
		$this->database = new SQLite3($this->getDataFolder()."data.db");
		$this->database->exec("CREATE TABLE IF NOT EXISTS players(username NOT NULL PRIMARY KEY, balance INT NOT NULL DEFAULT 0)");
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onDisable()
	{
		$this->database->close();
	}

	public static function getInstance()
	{
		return self::$instance;
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool
	{
		if (!$sender instanceof Player) {
			$sender->sendMessage("Bạn chỉ có thể sử dụng lệnh này trong game");
			return true;
		}
		switch ($command) {
			case "money":
				if (!count($args)) {
					$this->MoneyManagerForm($sender)->sendToPlayer($sender);
					return true;
				}
			
			default:
				return false;
		}
		
	}

	public function MoneyManagerForm(Player $sender)
	{
		$form = new SimpleForm(function(Player $sender, $data){
			if (is_numeric($data)) {
				switch ($data) {
					case 0:
						$this->SendMoneyToPlayerForm($sender)->sendToPlayer($sender);
						return;
					case 1:
						$this->TopRichForm()->sendToPlayer($sender);
						return;
					case 2:
						$this->EditMoneyPlayerForm($sender)->sendToPlayer($sender);
						return;
				}
			}
			return;
		});
		$balance = $this->getBalanceByName($sender->getName())->fetchArray(SQLITE3_ASSOC);
		if (is_bool($balance)) {
			$balance = ["balance" => 0];
		}
		$form->setTitle("§l§cGiao Diện Quản Lý Tiền");
		$form->setContent(implode("\n", [
			"§l§eTên: §b".$sender->getName(),
			"§l§eSố Tiền Đang Có: §6".$balance["balance"]." VNĐ"
		]));
		$form->addButton("§l§cChuyển Tiền Cho Người Chơi Khác");
		$form->addButton("§l§cXem Bảng Xếp Hạng Đại Gia");
		if ($sender->isOp()) {
			$form->addButton("§l§cChỉnh Sửa Tiền Của Người Chơi");
		}

		return $form;
	}

	public function SendMoneyToPlayerForm(Player $sender)
	{
		$form = new CustomForm(function(Player $sender, $data){
			if ($data && count($data) == 2) {
				if (!$sender->getServer()->getPlayer($data[0])) {
					$sender->sendMessage("§l§cKhông Tìm Thấy Người Chơi ".$data[0]."!");
					return;
				}
				if (!is_int($data[1])) {
					$sender->sendMessage("§l§cSố Tiền Bạn Đã Nhập Sai Định Dạng!");
					return;
				}
				$playerSourceName = $sender->getName();
				$playerDestName = $sender->getServer()->getPlayer($data[0])->getName();
				$quantity = $data[1] >= 1000 ? $data[1] : 1000;
				if ($this->getBalanceByName($playerSourceName)->fetchArray(SQLITE3_ASSOC)[0] < $quantity) {
					$sender->sendMessage("§l§cTài Khoản Của Bạn Không Đủ ".$quantity." VNĐ Để Chuyển Tiền!");
					return;
				}

				$this->transferMoney($playerSourceName, $playerDestName, $quantity);
				$sender->sendMessage("§l§aBạn Đã Chuyển Thành Công ".$quantity." VNĐ Cho ".$playerDestName."!");
				$sender->getServer()->getPlayer($data[0])->sendMessage("§l§aBạn Đã Nhận Thành Công ".$quantity." VNĐ Của ".$playerSourceName."!");
			}
			return;
		});
		$form->setTitle("§l§cChuyển Tiền Cho Người Chơi Khác");
		$form->addInput("§l§bTên Người Chơi", "§l§bTên Người Chơi", "");
		$form->addInput("§l§bSố Tiền Muốn Chuyển", "1000", "1000");
		$form->addLabel("* §lLưu Ý: Số Tiền Tối Thiểu Phải Là 1000VNĐ");
		return $form;
	}

	public function TopRichForm()
	{
		$form = new CustomForm(null);
		$form->setTitle("§l§cBảng Xếp Hạng Đại Gia (100)");
		$topRich = $this->getHighestMoney(100);
		while ($value = $topRich->fetchArray(SQLITE3_ASSOC)) {
			$form->addLabel("§l§b".$value["username"]." §6".$value["balance"]. " VNĐ\n");
		}
		return $form;
	}

	public function EditMoneyPlayerForm(Player $sender)
	{
		$form = new CustomForm(function(Player $sender, $data){
			if ($data && count($data) == 3) {
				if (!$sender->getServer()->getPlayer($data[0])) {
					$sender->sendMessage("§l§cKhông Tìm Thấy Người Chơi ".$data[0]."!");
					return;
				}
				$status = $this->setMoney($data[1], $data[0], intval($data[2]));
				$sender->sendMessage($status);
			}
			return;
		});
		$form->setTitle("§l§cCài Đặt Tiền Của Người Chơi");
		$form->addInput("§l§bTên Người Chơi", $sender->getName(), $sender->getName());
		$form->addToggle("§l§bCộng, Trừ / Chỉnh Trực Tiếp");
		$form->addInput("§l§bSố Tiền");
		return $form;
	}

	public function onPlayerJoin(PlayerJoinEvent $event)
	{
		$this->database->exec("INSERT OR IGNORE INTO 'players' VALUES ('".$event->getPlayer()->getName()."',0)");
	}

	public function getBalanceByName(string $name)
	{
		return $this->database->query("SELECT balance FROM 'players' WHERE username=='".$name."'");
	}

	public function getHighestMoney(int $limit = 100)
	{
		return $this->database->query("SELECT * FROM 'players' ORDER BY balance DESC LIMIT ".strval($limit));
	}

	public function transferMoney(string $source, string $dest, int $quantity)
	{
		$sourceCalculated = $this->getBalanceByName($source)->fetchArray(SQLITE3_ASSOC)["balance"] - $quantity;
		$destCalculated = $this->getBalanceByName($dest)->fetchArray(SQLITE3_ASSOC)["balance"] + $quantity;
		$this->database->exec("UPDATE 'players' SET balance = ".$sourceCalculated." WHERE username = '".$source."'");
		$this->database->exec("UPDATE 'players' SET balance = ".$destCalculated." WHERE username = '".$dest."'");
	}

	public function setMoney(bool $mode, string $dest, int $quantity): string
	{
		$balance = $this->getBalanceByName($dest)->fetchArray(SQLITE3_ASSOC)["balance"];
		$destCalculated = $balance + $quantity;
		if (!$mode) {
			if ($destCalculated < 0) {
				return "§l§cBạn Không Thể Trừ Quá Số Tiền Đang Có Trong Tài Khoản!";
			}
			$this->database->exec("UPDATE 'players' SET balance = ".$destCalculated." WHERE username = '".$dest."'");
		} else {
			$this->database->exec("UPDATE 'players' SET balance = ".$quantity." WHERE username = '".$dest."'");
		}
		return "§l§aThành Công!";
	}
}
