<?php 
namespace worldEditor;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\Config;
use pocketmine\item\ItemIds;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\block\BlockFactory;

class Main extends PluginBase{
	private $sessions, $path, $config,$exportPath;

	public function onEnable(){
		$this->getLogger()->info("Hello World!");
		$this->path = $this->getDataFolder();
		$this->config = new Config($this->path."config.yml", Config::YAML, array(
			"block-limit" => -1,
			"wand-item" => ItemIds::IRON_HOE,
		));	
		$this->exportPath  = $this->path .  DIRECTORY_SEPARATOR  . "exports" ;
		if(!file_exists($this->exportPath)){
			mkdir($this->exportPath,0777,true);
		}
	}
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$output = "";
		switch($command->getName()){
			case "examplecommand":
				$sender->sendMessage("Example command output");
				return true;
			case "pos1":
				if(!($sender instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					$sender->sendMessage($output);
					return false;
				}
				$this->setPosition1($this->session($sender),$sender->getPosition(),$output); 
				$sender->sendMessage($output);
				return true;
			case "pos2":
				$this->setPosition2($this->session($sender),$sender->getPosition(),$output); 
				$sender->sendMessage($output);
				return true;
			case "paste":
				if(!($sender instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					return false;
				}
				$session =& $this->session($sender);
				
				$this->W_paste($session["clipboard"], $sender->getPosition(), $output);
				return true;
			case "copy":
				if(!($sender instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					return false;
				}
				$session =& $this->session($sender);
				$count = $this->countBlocks($session["selection"], $startX, $startY, $startZ);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to copy $count block(s).\n";
					return false;
				}
				
				$blocks = $this->W_copy($session["selection"], $output);
				if(count($blocks) > 0){
					$offset = array($startX - $sender->getPosition()->x - 0.5, $startY - $sender->getPosition()->y, $startZ - $sender->getPosition()->z - 0.5);
					$session["clipboard"] = array($offset, $blocks);
				}
				return true;
			case "export":
				$session =& $this->session($sender);
				$this->W_save($session["clipboard"],$args[0]);
				return true;
			case "listexports":
				$files = scandir($this->exportPath);
				foreach ($files as $value)
				{
					$sender->sendMessage($value);
				}				
				return true;
		default:
				return false;
		}
	}
	public function onDisable(){
		$this->getLogger()->info("Bye");
	}



	public function W_save($clipboard,$name){
		$path = $this->exportPath ;
		$fp = fopen(  $path . '.json', 'w');
		fwrite($fp, json_encode($clipboard));
		fclose($fp);		
		return;
	}
	public function &session(Player $issuer){
		if(!isset($this->sessions[$issuer->getDisplayName()])){
			$this->sessions[$issuer->getDisplayName()] = array(
				"selection" => array(false, false),
				"clipboard" => false,
				"block-limit" => $this->config->get("block-limit"),
				"wand-usage" => true,
			);
		}
		return $this->sessions[$issuer->getDisplayName()];
	}
	
	public function setPosition1(&$session, Position $position, &$output){
		$session["selection"][0] = array(round($position->x), round($position->y), round($position->z), $position->level);
		$count = $this->countBlocks($session["selection"]);
		if($count === false){
			$count = "";
		}else{
			$count = " ($count)";
		}
		$output .= "First position set to (".$session["selection"][0][0].", ".$session["selection"][0][1].", ".$session["selection"][0][2].")$count.\n";
		return true;
	}
	
	public function setPosition2(&$session, Position $position, &$output){
		$session["selection"][1] = array(round($position->x), round($position->y), round($position->z), $position->level);
		$count = $this->countBlocks($session["selection"]);
		if($count === false){
			$count = "";
		}else{
			$count = " ($count)";
		}
		$output .= "Second position set to (".$session["selection"][1][0].", ".$session["selection"][1][1].", ".$session["selection"][1][2].")$count.\n";
		return true;
	}
	
	public function command($cmd, $params, $issuer, $alias){
		$output = "";
		if($alias !== false){
			$cmd = $alias;
		}
		if($cmd{0} === "/"){
			$cmd = substr($cmd, 1);
		}
		
		switch($cmd){
			case "cut":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"], $startX, $startY, $startZ);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to cut $count block(s).\n";
					break;
				}
				
				$blocks = $this->W_cut($session["selection"], $output);
				if(count($blocks) > 0){
					$offset = array($startX - $issuer->entity->x - 0.5, $startY - $issuer->entity->y, $startZ - $issuer->entity->z - 0.5);
					$session["clipboard"] = array($offset, $blocks);
				}
				break;
			case "toggleeditwand":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$session["wand-usage"] = $session["wand-usage"] == true ? false:true;
				$output .= "Wand Item is now ".($session["wand-usage"] === true ? "enabled":"disabled").".\n";
				break;
			case "wand":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if($issuer->hasItem($this->config->get("wand-item"))){
					$output .= "You already have the wand item.\n";
					break;
				}elseif($issuer->gamemode === CREATIVE){
					$output .= "You are on creative mode.\n";
				}else{
					$this->api->entity->drop(new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), BlockAPI::getItem($this->config->get("wand-item")));
				}
				$output .= "Break a block to set the #1 position, place for the #1.\n";
				break;
			case "desel":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$session["selection"] = array(false, false);
				$output = "Selection cleared.\n";
				break;
			case "limit":
				if(!isset($params[0]) or trim($params[0]) === ""){
					$output .= "Usage: //limit <limit>\n";
					break;
				}
				$limit = intval($params[0]);
				if($limit < 0){
					$limit = -1;
				}
				if($this->config->get("block-limit") > 0){
					$limit = $limit === -1 ? $this->config->get("block-limit"):min($this->config->get("block-limit"), $limit);
				}
				$this->session($issuer)["block-limit"] = $limit;
				$output .= "Block limit set to ".($limit === -1 ? "infinite":$limit)." block(s).\n";
				break;

			case "hsphere":
				$filled = false;
			case "sphere":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				if(!isset($filled)){
					$filled = true;
				}
				if(!isset($params[1]) or $params[1] == ""){
					$output .= "Usage: //$cmd <block> <radius>.\n";
					break;
				}
				$radius = abs(floatval($params[1]));
				
				$session =& $this->session($issuer);
				$items = BlockAPI::fromString($params[0], true);
				
				foreach($items as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect block.\n";
						return $output;
					}
				}
				$this->W_sphere(new Position($issuer->entity->x - 0.5, $issuer->entity->y, $issuer->entity->z - 0.5, $issuer->entity->level), $items, $radius, $radius, $radius, $filled, $output);
				break;
			case "set":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"]);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to change $count block(s).\n";
					break;
				}
				$items = BlockAPI::fromString($params[0], true);
				foreach($items as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect block.\n";
						return $output;
					}
				}
				$this->W_set($session["selection"], $items, $output);
				break;
			case "replace":
				if(!($issuer instanceof Player)){					
					$output .= "Please run this command in-game.\n";
					break;
				}
				$session =& $this->session($issuer);
				$count = $this->countBlocks($session["selection"]);
				if($count > $session["block-limit"] and $session["block-limit"] > 0){
					$output .= "Block limit of ".$session["block-limit"]." exceeded, tried to change $count block(s).\n";
					break;
				}
				$item1 = BlockAPI::fromString($params[0]);
				if($item1->getID() > 0xff){
					$output .= "Incorrect target block.\n";
					break;
				}
				$items2 = BlockAPI::fromString($params[1], true);
				foreach($items2 as $item){
					if($item->getID() > 0xff){
						$output .= "Incorrect replacement block.\n";
						return $output;
					}
				}
				
				$this->W_replace($session["selection"], $item1, $items2, $output);
				break;
			default:
			case "help":
				$output .= "Commands: //cut, //copy, //paste, //sphere, //hsphere, //desel, //limit, //pos1, //pos2, //set, //replace, //help, //wand, /toggleeditwand\n";
				break;
		}
		return $output;
	}
	
	private function countBlocks($selection, &$startX = null, &$startY = null, &$startZ = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			return false;
		}
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		return ($endX - $startX + 1) * ($endY - $startY + 1) * ($endZ - $startZ + 1);
	}

	private function W_paste($clipboard, Position $pos, &$output = null){
		if(count($clipboard) !== 2){
			$output .= "Copy something first.\n";
			return false;
		}
		$clipboard[0][0] += $pos->x - 0.5;
		$clipboard[0][1] += $pos->y;
		$clipboard[0][2] += $pos->z - 0.5;
		$offset = array_map("round", $clipboard[0]);
		$count = 0;
		
		foreach($clipboard[1] as $x => $i){
			foreach($i as $y => $j){
				foreach($j as $z => $block){
					$b = BlockFactory::get(ord($block{0}), ord($block{1}));
					$count += (int) $pos->level->setBlock(new Vector3($x + $offset[0], $y + $offset[1], $z + $offset[2]), $b, false);
					unset($b);
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
	
	private function W_copy($selection, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return array();
		}
		$level = $selection[0][3];
		
		$blocks = array();
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = $this->countBlocks($selection);
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = chr($b->getID()).chr($b->getDamage());
					unset($b);
				}
			}
		}
		$output .= "$count block(s) have been copied.\n";
		return $blocks;
	}
	
	private function W_cut($selection, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return array();
		}
		$totalCount = $this->countBlocks($selection);
		if($totalCount > 524288){
			$send = false;
		}else{
			$send = true;
		}
		$level = $selection[0][3];
		
		$blocks = array();
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = $this->countBlocks($selection);
		$air = new AirBlock();
		for($x = $startX; $x <= $endX; ++$x){
			$blocks[$x - $startX] = array();
			for($y = $startY; $y <= $endY; ++$y){
				$blocks[$x - $startX][$y - $startY] = array();
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					$blocks[$x - $startX][$y - $startY][$z - $startZ] = chr($b->getID()).chr($b->getMetadata());
					$level->setBlockRaw(new Vector3($x, $y, $z), $air, false, $send);
					unset($b);
				}
			}
		}
		if($send === false){
			$forceSend = function($X, $Y, $Z){
				$this->changedCount[$X.":".$Y.":".$Z] = 4096;
			};			
			$forceSend->bindTo($level, $level);
			for($X = $startX >> 4; $X <= ($endX >> 4); ++$X){
				for($Y = $startY >> 4; $Y <= ($endY >> 4); ++$Y){
					for($Z = $startZ >> 4; $Z <= ($endZ >> 4); ++$Z){
						$forceSend($X,$Y,$Z);
					}
				}
			}
		}
		$output .= "$count block(s) have been cut.\n";
		return $blocks;
	}
	
	private function W_set($selection, $blocks, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return false;
		}
		$totalCount = $this->countBlocks($selection);
		if($totalCount > 524288){
			$send = false;
		}else{
			$send = true;
		}
		$level = $selection[0][3];
		$bcnt = count($blocks) - 1;
		$bcnt2 = count($blocks2) - 1;
		if($bcnt < 0){
			$output .= "Incorrect blocks.\n";
			return false;
		}
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = 0; //$count = $this->countBlocks($selection);
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $blocks[mt_rand(0, $bcnt)];
					$count += (int) $level->setBlockRaw(new Vector3($x, $y, $z), $b->getBlock(), false, $send);
				}
			}
		}
		if($send === false){
			$forceSend = function($X, $Y, $Z){
				$this->changedCount[$X.":".$Y.":".$Z] = 4096;
			};			
			$forceSend->bindTo($level, $level);
			for($X = $startX >> 4; $X <= ($endX >> 4); ++$X){
				for($Y = $startY >> 4; $Y <= ($endY >> 4); ++$Y){
					for($Z = $startZ >> 4; $Z <= ($endZ >> 4); ++$Z){
						$forceSend($X,$Y,$Z);
					}
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
	
	private function W_replace($selection, Item $block1, $blocks2, &$output = null){
		if(!is_array($selection) or $selection[0] === false or $selection[1] === false or $selection[0][3] !== $selection[1][3]){
			$output .= "Make a selection first.\n";
			return false;
		}
		
		$totalCount = $this->countBlocks($selection);
		if($totalCount > 524288){
			$send = false;
		}else{
			$send = true;
		}
		$level = $selection[0][3];
		$id1 = $block1->getID();
		$meta1 = $block1->getMetadata();
		
		$bcnt2 = count($blocks2) - 1;
		if($bcnt2 < 0){
			$output .= "Incorrect blocks.\n";
			return false;
		}
		
		$startX = min($selection[0][0], $selection[1][0]);
		$endX = max($selection[0][0], $selection[1][0]);
		$startY = min($selection[0][1], $selection[1][1]);
		$endY = max($selection[0][1], $selection[1][1]);
		$startZ = min($selection[0][2], $selection[1][2]);
		$endZ = max($selection[0][2], $selection[1][2]);
		$count = 0;
		for($x = $startX; $x <= $endX; ++$x){
			for($y = $startY; $y <= $endY; ++$y){
				for($z = $startZ; $z <= $endZ; ++$z){
					$b = $level->getBlock(new Vector3($x, $y, $z));
					if($b->getID() === $id1 and ($meta1 === false or $b->getMetadata() === $meta1)){
						$count += (int) $level->setBlockRaw($b, $blocks2[mt_rand(0, $bcnt2)]->getBlock(), false, $send);
					}
					unset($b);
				}
			}
		}
		if($send === false){
			$forceSend = function($X, $Y, $Z){
				$this->changedCount[$X.":".$Y.":".$Z] = 4096;
			};			
			$forceSend->bindTo($level, $level);
			for($X = $startX >> 4; $X <= ($endX >> 4); ++$X){
				for($Y = $startY >> 4; $Y <= ($endY >> 4); ++$Y){
					for($Z = $startZ >> 4; $Z <= ($endZ >> 4); ++$Z){
						$forceSend($X,$Y,$Z);
					}
				}
			}
		}
		$output .= "$count block(s) have been changed.\n";
		return true;
	}
	
	public static function lengthSq($x, $y, $z){
		return ($x * $x) + ($y * $y) + ($z * $z);
	}
	
	private function W_sphere(Position $pos, $blocks, $radiusX, $radiusY, $radiusZ, $filled = true, &$output = null){
		$count = 0;

        $radiusX += 0.5;
        $radiusY += 0.5;
        $radiusZ += 0.5;

        $invRadiusX = 1 / $radiusX;
        $invRadiusY = 1 / $radiusY;
        $invRadiusZ = 1 / $radiusZ;

        $ceilRadiusX = (int) ceil($radiusX);
        $ceilRadiusY = (int) ceil($radiusY);
        $ceilRadiusZ = (int) ceil($radiusZ);

		$bcnt = count($blocks) - 1;
		
        $nextXn = 0;
		$breakX = false;
		for($x = 0; $x <= $ceilRadiusX and $breakX === false; ++$x){
			$xn = $nextXn;
			$nextXn = ($x + 1) * $invRadiusX;
			$nextYn = 0;
			$breakY = false;
			for($y = 0; $y <= $ceilRadiusY and $breakY === false; ++$y){
				$yn = $nextYn;
				$nextYn = ($y + 1) * $invRadiusY;
				$nextZn = 0;
				$breakZ = false;
				for($z = 0; $z <= $ceilRadiusZ; ++$z){
					$zn = $nextZn;
					$nextZn = ($z + 1) * $invRadiusZ;
					$distanceSq = WorldEditor::lengthSq($xn, $yn, $zn);
					if($distanceSq > 1){
						if($z === 0){
							if($y === 0){
								$breakX = true;
								$breakY = true;
								break;
							}
							$breakY = true;
							break;
						}
						break;
					}
					
					if($filled === false){						
						if(WorldEditor::lengthSq($nextXn, $yn, $zn) <= 1 and WorldEditor::lengthSq($xn, $nextYn, $zn) <= 1 and WorldEditor::lengthSq($xn, $yn, $nextZn) <= 1){
							continue;
						}
					}					

					$count += (int) $pos->level->setBlockRaw($pos->add($x, $y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, $y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add($x, -$y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add($x, $y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, -$y, $z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add($x, -$y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, $y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					$count += (int) $pos->level->setBlockRaw($pos->add(-$x, -$y, -$z), $blocks[mt_rand(0, $bcnt)]->getBlock(), false);
					
				}
			}
		}
		
		$output .= "$count block(s) have been changed.\n";
		return true;	
	}


}