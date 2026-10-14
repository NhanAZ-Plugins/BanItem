<?php

declare(strict_types=1);

namespace pocketmine\command{
	class Command{
		public function __construct(private string $name){}
		public function getName() : string{ return $this->name; }
	}
	class CommandSender{
		/** @var list<string> */
		public array $messages = [];
		public function sendMessage(string $message) : void{ $this->messages[] = $message; }
	}
}

namespace pocketmine\event{
	interface Listener{}
}

namespace pocketmine\event\block{
	class BlockPlaceEvent{
		public bool $cancelled = false;
		public function __construct(private \pocketmine\player\Player $player, private \pocketmine\item\Item $item){}
		public function getPlayer() : \pocketmine\player\Player{ return $this->player; }
		public function getItem() : \pocketmine\item\Item{ return $this->item; }
		public function cancel() : void{ $this->cancelled = true; }
	}
}

namespace pocketmine\event\entity{
	class EntityDamageEvent{
		public bool $cancelled = false;
		public function cancel() : void{ $this->cancelled = true; }
	}
	class EntityDamageByEntityEvent extends EntityDamageEvent{
		public function __construct(private object $damager){}
		public function getDamager() : object{ return $this->damager; }
	}
	class EntityShootBowEvent{
		public bool $cancelled = false;
		public function __construct(private object $entity, private \pocketmine\item\Item $bow){}
		public function getEntity() : object{ return $this->entity; }
		public function getBow() : \pocketmine\item\Item{ return $this->bow; }
		public function cancel() : void{ $this->cancelled = true; }
	}
}

namespace pocketmine\event\player{
	class PlayerInteractEvent{
		public bool $cancelled = false;
		public function __construct(private \pocketmine\player\Player $player, private \pocketmine\item\Item $item){}
		public function getPlayer() : \pocketmine\player\Player{ return $this->player; }
		public function getItem() : \pocketmine\item\Item{ return $this->item; }
		public function cancel() : void{ $this->cancelled = true; }
	}
	class PlayerItemConsumeEvent{
		public bool $cancelled = false;
		public function __construct(private \pocketmine\player\Player $player, private \pocketmine\item\Item $item){}
		public function getPlayer() : \pocketmine\player\Player{ return $this->player; }
		public function getItem() : \pocketmine\item\Item{ return $this->item; }
		public function cancel() : void{ $this->cancelled = true; }
	}
}

namespace pocketmine\item{
	class Item{
		public function __construct(private int $stateId){}
		public function getStateId() : int{ return $this->stateId; }
	}
	class StringToItemParser{
		private static ?self $instance = null;
		/** @var array<string, Item> */
		private array $items = [];
		public static function getInstance() : self{ return self::$instance ??= new self(); }
		public function register(string $identifier, Item $item) : void{ $this->items[$identifier] = $item; }
		public function parse(string $identifier) : ?Item{ return $this->items[$identifier] ?? null; }
	}
	class LegacyStringToItemParserException extends \RuntimeException{}
	class LegacyStringToItemParser{
		private static ?self $instance = null;
		/** @var array<string, Item> */
		private array $items = [];
		public static function getInstance() : self{ return self::$instance ??= new self(); }
		public function register(string $identifier, Item $item) : void{ $this->items[$identifier] = $item; }
		public function parse(string $identifier) : Item{
			return $this->items[$identifier] ?? throw new LegacyStringToItemParserException($identifier);
		}
	}
}

namespace pocketmine\player{
	class Inventory{
		public function __construct(private \pocketmine\item\Item $item){}
		public function getItemInHand() : \pocketmine\item\Item{ return $this->item; }
	}
	class Player{
		/** @var list<string> */
		public array $messages = [];
		/** @param list<string> $permissions */
		public function __construct(private Inventory $inventory, private array $permissions = []){}
		public function getInventory() : Inventory{ return $this->inventory; }
		public function hasPermission(string $permission) : bool{ return in_array($permission, $this->permissions, true); }
		public function sendMessage(string $message) : void{ $this->messages[] = $message; }
	}
}

namespace pocketmine\plugin{
	class PluginBase{
		public function __construct(private string $dataFolder, private object $logger, private object $server){}
		public function getDataFolder() : string{ return $this->dataFolder; }
		public function getLogger() : object{ return $this->logger; }
		public function getServer() : object{ return $this->server; }
	}
}

namespace pocketmine\utils{
	class TextFormat{
		public const RED = "<red>";
		public const YELLOW = "<yellow>";
		public const GREEN = "<green>";
		public const AQUA = "<aqua>";
		public const WHITE = "<white>";
	}
}

namespace{
	use Ghast\BanItem\Main;
	use pocketmine\command\Command;
	use pocketmine\command\CommandSender;
	use pocketmine\event\player\PlayerInteractEvent;
	use pocketmine\item\Item;
	use pocketmine\item\LegacyStringToItemParser;
	use pocketmine\item\StringToItemParser;
	use pocketmine\player\Inventory;
	use pocketmine\player\Player;

	require dirname(__DIR__) . "/src/Ghast/BanItem/Main.php";

	function expect(bool $condition, string $message) : void{
		if(!$condition){
			throw new RuntimeException($message);
		}
	}

	$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "banitem-api5-" . bin2hex(random_bytes(6));
	$dataFolder = $temporaryRoot . DIRECTORY_SEPARATOR . "plugin_data" . DIRECTORY_SEPARATOR . "BanItem" . DIRECTORY_SEPARATOR;
	if(!mkdir($dataFolder, 0777, true) && !is_dir($dataFolder)){
		throw new RuntimeException("Could not create isolated test data directory");
	}

	$modern = StringToItemParser::getInstance();
	$legacy = LegacyStringToItemParser::getInstance();
	$diamondSword = new Item(276);
	$goldenApple = new Item(322);
	$apple = new Item(260);
	$modern->register("minecraft:diamond_sword", $diamondSword);
	$modern->register("minecraft:golden_apple", $goldenApple);
	$modern->register("minecraft:apple", $apple);
	$legacy->register("276:0", $diamondSword);
	$legacy->register("280", new Item(280));

	$storedItems = [276, "280", "276#0", "minecraft:golden_apple", "old:unsupported"];
	file_put_contents($dataFolder . "items.bin", json_encode($storedItems, JSON_THROW_ON_ERROR));
	$logger = new class{
		/** @var list<string> */
		public array $warnings = [];
		public function warning(string $message) : void{ $this->warnings[] = $message; }
	};
	$manager = new class{ public function registerEvents(object $listener, object $plugin) : void{} };
	$server = new class($manager){
		public function __construct(private object $manager){}
		public function getPluginManager() : object{ return $this->manager; }
	};
	$plugin = new Main($dataFolder, $logger, $server);
	$loadItems = new ReflectionMethod(Main::class, "loadItems");
	expect($loadItems->invoke($plugin), "Legacy item list should load safely");
	expect($plugin->isBanned($diamondSword), "Legacy numeric and ID:meta aliases must match the item state");
	expect($plugin->isBanned($goldenApple), "Modern namespaced item names must match the item state");
	expect(count($logger->warnings) === 1, "Unsupported identifiers should be reported");
	expect(file_get_contents($dataFolder . "items.bin") === json_encode($storedItems, JSON_THROW_ON_ERROR), "Loading must not rewrite or discard stored identifiers");

	$operational = new ReflectionProperty(Main::class, "operational");
	$operational->setValue($plugin, true);
	$player = new Player(new Inventory($diamondSword));
	$interact = new PlayerInteractEvent($player, $diamondSword);
$plugin->onTouch($interact);
	expect($interact->cancelled, "A banned item interaction should be cancelled");
	$bypass = new Player(new Inventory($diamondSword), ["banitem.bypass"]);
	$bypassEvent = new PlayerInteractEvent($bypass, $diamondSword);
	$plugin->onTouch($bypassEvent);
	expect(!$bypassEvent->cancelled, "The bypass permission should still work");

	$commandSender = new CommandSender();
	$plugin->onCommand($commandSender, new Command("banitem"), "banitem", ["ban", "minecraft:apple"]);
	expect($plugin->isBanned($apple), "The command should persist modern item names");
	expect(str_contains((string) file_get_contents($dataFolder . "items.bin"), "minecraft:apple"), "Saved identifiers should remain readable names");
	$plugin->onCommand($commandSender, new Command("banitem"), "banitem", ["unban", "minecraft:apple"]);
	expect(!$plugin->isBanned($apple), "Unban should remove the matching item state");
	expect(count(json_decode((string) file_get_contents($dataFolder . "items.bin"), true, flags: JSON_THROW_ON_ERROR)) === count($storedItems), "Unban should preserve unrelated legacy and unsupported entries");

	file_put_contents($dataFolder . "items.bin", "{invalid-json");
	expect(!$loadItems->invoke($plugin), "Malformed persisted data must fail closed");
	expect(file_get_contents($dataFolder . "items.bin") === "{invalid-json", "Malformed data must not be overwritten");

	unlink($dataFolder . "items.bin");
	rmdir($dataFolder);
	rmdir(dirname($dataFolder));
	rmdir($temporaryRoot);
	echo "BanItem API 5 behavior checks passed.\n";
}
