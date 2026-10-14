<?php

declare(strict_types=1);

namespace Ghast\BanItem;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\item\Item;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use function array_is_list;
use function array_map;
use function array_search;
use function array_splice;
use function count;
use function file_get_contents;
use function file_put_contents;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function str_replace;
use function strtolower;
use function unlink;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

final class Main extends PluginBase implements Listener{
	/** @var list<int|string> */
	private array $items = [];

	/** @var list<int|null> */
	private array $itemStateIds = [];

	private bool $operational = false;

	protected function onEnable() : void{
		if(!$this->loadItems()){
			$this->getLogger()->critical("BanItem could not load its existing item list safely; item data was not overwritten and event checks are disabled.");
			return;
		}

		$this->operational = true;
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onTouch(PlayerInteractEvent $event) : void{
		if($this->shouldBlock($event->getPlayer(), $event->getItem())){
			$event->cancel();
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void{
		if($this->shouldBlock($event->getPlayer(), $event->getItem())){
			$event->cancel();
		}
	}

	public function onHurt(EntityDamageEvent $event) : void{
		if($event instanceof EntityDamageByEntityEvent && $event->getDamager() instanceof Player){
			$player = $event->getDamager();
			if($this->shouldBlock($player, $player->getInventory()->getItemInHand())){
				$event->cancel();
			}
		}
	}

	public function onEat(PlayerItemConsumeEvent $event) : void{
		if($this->shouldBlock($event->getPlayer(), $event->getItem())){
			$event->cancel();
		}
	}

	public function onShoot(EntityShootBowEvent $event) : void{
		$entity = $event->getEntity();
		if($entity instanceof Player && $this->shouldBlock($entity, $event->getBow())){
			$event->cancel();
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(strtolower($command->getName()) !== "banitem"){
			return false;
		}
		if(!$this->operational){
			$sender->sendMessage(TextFormat::RED . "BanItem is unavailable because its item data could not be loaded safely.");
			return true;
		}
		if(!isset($args[0])){
			return false;
		}

		$action = strtolower($args[0]);
		if($action === "list"){
			$displayItems = array_map(static fn(int|string $identifier) : string => str_replace("#", ":", (string) $identifier), $this->items);
			$sender->sendMessage(TextFormat::AQUA . "Banned items: " . (count($displayItems) > 0 ? implode(", ", $displayItems) : "None"));
			return true;
		}
		if($action !== "ban" && $action !== "unban"){
			return false;
		}
		if(!isset($args[1])){
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /banitem " . $action . " <item name or legacy ID[:meta]>");
			return true;
		}

		$identifier = $args[1];
		$item = $this->parseItemIdentifier($identifier);
		if($item === null){
			$sender->sendMessage(TextFormat::RED . "That item name or legacy ID is not recognized by this Axolotl-PM version.");
			return true;
		}
		$stateId = $item->getStateId();

		if($action === "ban"){
			if(in_array($stateId, $this->itemStateIds, true)){
				$sender->sendMessage(TextFormat::YELLOW . "That item is already banned.");
				return true;
			}
			$this->items[] = $identifier;
			$this->itemStateIds[] = $stateId;
			if(!$this->saveItems()){
				array_splice($this->items, -1);
				array_splice($this->itemStateIds, -1);
				$sender->sendMessage(TextFormat::RED . "The item could not be saved, so the ban was not applied.");
				return true;
			}
			$sender->sendMessage(TextFormat::GREEN . "Banned item " . $identifier . ".");
			return true;
		}

		$index = array_search($stateId, $this->itemStateIds, true);
		if($index === false){
			$sender->sendMessage(TextFormat::YELLOW . "That item is not in the banned list.");
			return true;
		}
		$removedIdentifier = $this->items[$index];
		$removedStateId = $this->itemStateIds[$index];
		array_splice($this->items, $index, 1);
		array_splice($this->itemStateIds, $index, 1);
		if(!$this->saveItems()){
			array_splice($this->items, $index, 0, [$removedIdentifier]);
			array_splice($this->itemStateIds, $index, 0, [$removedStateId]);
			$sender->sendMessage(TextFormat::RED . "The item could not be saved, so its ban was kept.");
			return true;
		}
		$sender->sendMessage(TextFormat::GREEN . "Unbanned item " . $identifier . ".");
		return true;
	}

	public function isBanned(Item $item) : bool{
		return in_array($item->getStateId(), $this->itemStateIds, true);
	}

	private function shouldBlock(Player $player, Item $item) : bool{
		if(!$this->operational || !$this->isBanned($item) || $player->hasPermission("banitem") || $player->hasPermission("banitem.bypass")){
			return false;
		}
		$player->sendMessage(TextFormat::WHITE . "[" . TextFormat::RED . "This item is banned" . TextFormat::WHITE . "]");
		return true;
	}

	private function loadItems() : bool{
		$path = $this->getDataFolder() . "items.bin";
		if(!is_file($path)){
			$this->items = [];
			$this->itemStateIds = [];
			return $this->saveItems();
		}

		$contents = file_get_contents($path);
		if(!is_string($contents)){
			return false;
		}
		try{
			$decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
		}catch(\JsonException $exception){
			return false;
		}
		if(!is_array($decoded) || !array_is_list($decoded)){
			return false;
		}

		$this->items = [];
		$this->itemStateIds = [];
		$unrecognized = 0;
		foreach($decoded as $identifier){
			if(!is_int($identifier) && !is_string($identifier)){
				return false;
			}
			$this->items[] = $identifier;
			$item = $this->parseItemIdentifier((string) $identifier);
			$this->itemStateIds[] = $item?->getStateId();
			if($item === null){
				$unrecognized++;
			}
		}
		if($unrecognized > 0){
			$this->getLogger()->warning("$unrecognized stored item identifier(s) are not recognized by this server version. They remain preserved in items.bin and are not enforced until mapped to a supported item name.");
		}
		return true;
	}

	private function saveItems() : bool{
		$directory = $this->getDataFolder();
		if(!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)){
			return false;
		}
		try{
			$json = json_encode($this->items, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
		}catch(\JsonException $exception){
			return false;
		}
		$path = $directory . "items.bin";
		$temporaryPath = $path . ".tmp";
		if(file_put_contents($temporaryPath, $json, LOCK_EX) !== strlen($json)){
			@unlink($temporaryPath);
			return false;
		}
		if(!@rename($temporaryPath, $path)){
			@unlink($temporaryPath);
			return false;
		}
		return true;
	}

	private function parseItemIdentifier(string $identifier) : ?Item{
		$normalized = str_replace("#", ":", $identifier);
		$item = StringToItemParser::getInstance()->parse($normalized);
		if($item !== null){
			return $item;
		}
		try{
			return LegacyStringToItemParser::getInstance()->parse($normalized);
		}catch(LegacyStringToItemParserException $exception){
			return null;
		}
	}
}
