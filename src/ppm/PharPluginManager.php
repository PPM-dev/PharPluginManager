<?php

namespace ppm;

/* PluginBase */
use pocketmine\plugin\PluginBase;

/* Server */
use pocketmine\Server;

/* Player */
use pocketmine\player\Player;

/* Utils */
use pocketmine\utils\Config;

/* Command */
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

/* Event */
use pocketmine\event\Listener;

/* util */
use pocketmine\utils\VersionString;

/* protocolinfo */
use pocketmine\network\mcpe\protocol\ProtocolInfo;

class PharPluginManager extends PluginBase implements Listener
{
    public $source;
    public $packagelist;
    public $plugin;

    public function onEnable() :void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->source = new Config($this->getDataFolder() . "source.yml", Config::YAML);
        $this->packagelist = new Config($this->getDataFolder() . "list.yml", Config::YAML);
        if (!$this->source->exists("repo")) {
            $this->source->set("repo", ["https://ppm.pages.dev/Database.json"]);
            $this->source->save();
            $this->source->reload();
        }
        @mkdir($this->getDataFolder()."plugins/");
        $this->getServer()->getPluginManager()->loadPlugins($this->getDataFolder()."plugins/");            
    }

  public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        if(!$sender instanceof Player) return True;
        switch (strtolower($command->getName())) {
            case "ppm":
                if(!isset($args[0])){
                    $sender->sendMessage("/ppm  <install | uninstall | update | upgrade | addrepo | delrepo> [args]");
                    return true;
                }
                
                switch($args[0]){
                    case "install":
                        if(!isset($args[1])){
                            $sender->sendMessage("プラグイン名を指定してください");
                            return true;
                        }
                        
                        $sender->sendMessage("インストールされているプラグインを検索中です");
                        
                        $result = glob($this->getDataFolder()."plugins/*.phar");
                        if(in_array($this->getDataFolder()."plugins/".$args[1].".phar", $result)){
                            $sender->sendMessage("そのプラグインは既にインストールされているようです");
                            return true;
                        }
                        
                        $sender->sendMessage("指定されたプラグインを検索中です");
                        $list = $this->packagelist->get("list");
                        if(!$this->checkplugininlist($list,$args[1])){
                            $sender->sendMessage("プラグインが見つかりませんでした");
                            $sender->sendMessage("入力値を確認するか、/ppm updateを実行してください");
                            return true;
                        }
                        
                        $sender->sendMessage("プラグインが現在使用中のバージョンで使用できるか確認しています。");
                        if(!$this->checkAPIversion($args[1],$this->getServer()->getApiVersion())){
                            $sender->sendMessage("指定されたプラグインは現在使用中のバージョンには対応していません");
                            $sender->sendMessage("30分後くらいに/ppm updateを実行してみてください");
                            $sender->sendMessage("それでも改善しない場合はレポジトリの管理者に問い合わせてください");
                            return true;
                        }
                        
                        $sender->sendMessage("指定のプラグインが現在のプロトコルで動作するか確認しています");
                                                
                        if(!$this->checkNetworkProtocol($args[1],$this->packagelist->get("list"),$sender,ProtocolInfo::CURRENT_PROTOCOL)){
                            $sender->sendMessage("指定されたプラグインは現在のネットワークプロトコルには対応していません");
                            $sender->sendMessage("PocketMine-MP.pharのアップデートをするか、デベロッパーの対応をお待ちください");
                            return true;
                        }
                        
                        $sender->sendMessage("プラグインのダウンロードを開始します");
                        $options = stream_context_create(array('ssl' => array(
                          'verify_peer'      => false,
                          'verify_peer_name' => false
                        )));

                        $result = @file_get_contents($list[$args[1]]["artifact_url"], false, $options);
                        if(!$result){
                            $sender->sendMessage("エラー:ダウンロードに失敗しました");
                            $sender->sendMessage("サーバに接続できないか、サーバーからエラーが返されました");
                            $sender->sendMessage("プラグインのインストールに失敗しました");
                            return True;
                        }
                        
                        $sender->sendMessage("プラグインを保存しています");
                        @file_put_contents($this->getDataFolder()."plugins/".$args[1].".phar",$result);
                        $sender->sendMessage("保存完了しました");
                                                
                        $sender->sendMessage("プラグインの依存関係を確認しています");
                        foreach($list[$args[1]]["deps"] as $dep){
                            
                            if(!$this->checkplugininlist($list,$dep["name"])){
                                $sender->sendMessage("エラー:".$args[1]."の依存プラグイン(".$dep["name"].")が見つかりません");
                                $sender->sendMessage("インストールに失敗しました");
                                return true;
                            }
                            
                            if(!$this->checkAPIversion($dep["name"],$this->getServer()->getApiVersion())){
                                $sender->sendMessage("エラー:".$args[1]."の依存プラグイン(".$dep["name"].")が現在使用しているAPIバージョンに対応していません");
                                $sender->sendMessage("インストールに失敗しました");
                                return true;
                            }
                            
                            if(!$this->checkNetworkProtocol($dep["name"],$this->packagelist->get("list"),$sender,ProtocolInfo::CURRENT_PROTOCOL)){
                                $sender->sendMessage("エラー:".$args[1]."の依存プラグイン(".$dep["name"].")は現在のネットワークプロトコルに対応していません");
                                $sender->sendMessage("インストールに失敗しました");
                                return true;
                            }
                            
                            $options = stream_context_create(array('ssl' => array(
                                    'verify_peer'      => false,
                                    'verify_peer_name' => false
                                )));
                            
                            $result = @file_get_contents($list[$args[1]]["artifact_url"], false, $options);
                            if(!$result){
                                $sender->sendMessage("エラー:依存関係のダウンロードに失敗しました");
                                $sender->sendMessage("サーバに接続できないか、サーバーからエラーが返されました");
                                $sender->sendMessage("プラグインのインストールに失敗しました");
                                return true;
                            }
                            $sender->sendMessage("依存プラグイン(".$dep["name"].")を保存しています");
                            @file_put_contents($this->getDataFolder()."plugins/".$args[1].".phar",$result);
                            $sender->sendMessage("保存完了しました");
                                                
                        }
                        
                        $sender->sendMessage("全ての処理が正常に行われました");
                        $sender->sendMessage("プラグインを有効化するにはサーバーを再起動してください");
                        break;
                    case "uninstall":
                        if(!isset($args[1])){
                            $sender->sendMessage("プラグイン名を指定してください");
                            return true;
                        }
                        $sender->sendMessage("インストールされているプラグインを検索中です");
                        $result = glob($this->getDataFolder()."plugins/*.phar");
                        if(!in_array($this->getDataFolder()."plugins/".$args[1].".phar", $result)){
                            $sender->sendMessage("そのプラグインは現在インストールされていないようです");
                            return true;
                        }
                        $sender->sendMessage("プラグインが見つかりました");
                        $sender->sendMessage("プラグインをアンインストール中です");
                        @unlink($this->getDataFolder()."plugins/".$args[1].".phar");
                        $sender->sendMessage("アンインストールしました");
                        $sender->sendMessage("変更を反映させるにはサーバーを再起動してください");
                        $sender->sendMessage("※設定ファイルは削除されません。手動で削除をお願いします");
                        break;
                    case "update":
                        $cache = [];
                        $i = 0;
                        foreach($this->source->get("repo") as $url){
                            $sender->sendMessage("通信開始:".$url);
                            $options = stream_context_create(array('ssl' => array(
                                'verify_peer'      => false,
                                'verify_peer_name' => false
                            )));
                                                    
                            $result = @file_get_contents($url, false, $options);
                            $sender->sendMessage("通信完了:".$url);
                            if(!$result){
                                $sender->sendMessage("エラー(通信失敗):".$url);
                                $sender->sendMessage("アップデート処理に失敗しました");
                                return true;
                            }
                            $sender->sendMessage("受信データ解析開始:".$url);
                            $result = json_decode($result,true);
                            $sender->sendMessage("受信データ解析終了:".$url);
                            $cache[$i] = $result; 
                            $i = $i +1;
                        }
                        $this->makelist($cache,$sender);
                        break;
                    case "upgrade":
                        if(!isset($args[1])){
                            $sender->sendMessage("プラグイン名を指定してください");
                            return true;
                        }
                        $sender->sendMessage("インストールされているプラグインを検索中です");
                        $result = glob($this->getDataFolder()."plugins/*.phar");
                        if(!in_array($this->getDataFolder()."plugins/".$args[1].".phar", $result)){
                            $sender->sendMessage("そのプラグインは現在インストールされていないようです");
                            return true;
                        }
                        $sender->sendMessage("プラグインが見つかりました");
                        $sender->sendMessage("指定されたプラグインを検索中です");
                        $list = $this->packagelist->get("list");
                        if(!$this->checkplugininlist($list,$args[1])){
                            $sender->sendMessage("プラグインが見つかりませんでした");
                            $sender->sendMessage("入力値を確認するか、/ppm updateを実行してください");
                            return true;
                        }
                        
                        $sender->sendMessage("プラグインが現在使用中のバージョンで使用できるか確認しています。");
                        if(!$this->checkAPIversion($args[1],$this->getServer()->getApiVersion())){
                            $sender->sendMessage("指定されたプラグインは現在使用中のバージョンには対応していません");
                            $sender->sendMessage("30分後に/ppm updateを実行してみてください");
                            $sender->sendMessage("それでも改善しない場合はレポジトリの管理者に問い合わせてください");
                            return true;
                        }
                        
                        
                        $sender->sendMessage("指定のプラグインが現在のプロトコルで動作するか確認しています");
                        
                        if(!$this->checkNetworkProtocol($args[1],$this->packagelist->get("list"),$sender,ProtocolInfo::CURRENT_PROTOCOL)){
                            $sender->sendMessage("指定されたプラグインは現在のネットワークプロトコルには対応していません");
                            $sender->sendMessage("PocketMine-MP.pharのアップデートをするか、デベロッパーの対応をお待ちください");
                            return true;
                        }
                        
                        $sender->sendMessage("プラグインのダウンロードを開始します");
                        $options = stream_context_create(array('ssl' => array(
                            'verify_peer'      => false,
                            'verify_peer_name' => false
                        )));
                                                
                        $result = @file_get_contents($list[$args[1]]["artifact_url"], false, $options);
                        if(!$result){
                            $sender->sendMessage("エラー:ダウンロードに失敗しました");
                            $sender->sendMessage("サーバに接続できないか、サーバーからエラーが返されました");
                            $sender->sendMessage("プラグインのインストールに失敗しました");
                        }
                        $sender->sendMessage("プラグインを保存しています");
                        @file_put_contents($this->getDataFolder()."plugins/".$args[1].".phar",$result);
                        $sender->sendMessage("保存完了しました");
                        $sender->sendMessage("プラグインを有効化するにはサーバーを再起動してください");
                        break;
                    case "addrepo":
                        if(!isset($args[1])){
                            $sender->sendMessage("urlを指定してください");
                            return true;
                        }
                        $sender->sendMessage("レポジトリの存在確認を行います");
                        $options = stream_context_create(array('ssl' => array(
                          'verify_peer'      => false,
                          'verify_peer_name' => false
                        )));
                        
                        $result = @file_get_contents($args[1]."Repo.json", false, $options);
                        preg_match("/[0-9]{3}/", $http_response_header[0], $stcode);
                        
                        if($stcode[0]!=200){
                            $sender->sendMessage("URL:".$args[1]."Repo.json\nは200以外のステータスコードを返しました");
                            $sender->sendMessage("レポジトリの管理者にお問合せください");
                            return true;
                        }
                        $sender->sendMessage("レポジトリの存在確認に成功しました");
                        $sender->sendMessage("レポジトリを登録しています");
                        $repoarray = $this->source->get("repo");
                        array_push($repoarray,$args[1]."Repo.json");
                        $this->source->set("repo",$repoarray);
                        $this->source->save();
                        $this->source->reload();
                        $sender->sendMessage("レポジトリの登録が完了しました");
                        $sender->sendMessage("変更を反映させるには /ppm update を実行してください");
                        break;
                    case "delrepo":
                        if(!isset($args[1])){
                            $sender->sendMessage("削除したいレポジトリを指定してください");
                            return true;
                        }
                        $sender->sendMessage("登録されているレポジトリを検索中です");
                        $repoarray = $this->source->get("repo");
                        if(!in_array($args[1]."Repo.json",$repoarray)){
                            $sender->sendMessage("指定されたレポジトリは登録されていないようです");
                            return true;
                        }
                        $sender->sendMessage("レポジトリを削除しています");
                        $result = array_diff($repoarray, array($args[1]."Repo.json"));
                        $result = array_values($result);
                        $this->source->set("repo",$result);
                        $this->source->save();
                        $this->source->reload();
                        $sender->sendMessage("レポジトリの削除に成功しました");
                        break;
                    default:
                        $sender->sendMessage("/ppm  <install | uninstall | update | upgrade | addrepo | delrepo> [args]");
                        break;
                        
                }
                break;
        }
        return true;
    }
    
    public function checkAPIversion($name,$api){
        $list = $this->packagelist->get("list");
        $apiversion = $list[$name]["api"];
        $from = $apiversion["from"];
        
    	$myVersion = new VersionString($api);
	    if(!is_array($from)){
	        $from = [$from];
	    }
		foreach($from as $versionStr){
			$version = new VersionString($versionStr);
			if($version->getBaseVersion() !== $myVersion->getBaseVersion()){
				if($version->getMajor() !== $myVersion->getMajor()){
					continue;
				}
		
				if($version->getMinor() > $myVersion->getMinor()){ 
					continue;
				}
		
				if($version->getMinor() === $myVersion->getMinor() and $version->getPatch() > $myVersion->getPatch()){ 
					continue;
				}
			}
		
			return true;
		}
		
		return false;
    }
    
    public function checkNetworkProtocol($name,$list,$sender,$protocol){
        try{
            
            if(@!isset($list[$name]["mcpe-protocol"])){
                $sender->sendMessage("指定されたプラグインにはネットワークプロトコルの指定は見つかりませんでした");
                $sender->sendMessage("インストールを続行します");
                return true;
            }
        
            $pluginprotocol = $list[$name]["mcpe-protocol"];
        
            if(!is_array($pluginprotocol)){
                $pluginprotocol = [$pluginprotocol];
            }
            
            foreach($pluginprotocol as $plprotocol){
                if($plprotocol == $protocol){
                    $sender->sendMessage("指定されたプラグインは現在のネットワークプロトコルに対応しています");
                    return true;
                }else{
                    continue;
                }
            }
            
            return false;
            
        }catch (Exception $e){
            $sender->sendMessage("指定されたプラグインにはネットワークプロトコルの指定は見つかりませんでした");
            $sender->sendMessage("インストールを続行します");
            return true;
        }
    }
    
    public function checkplugininlist($list,$name){
        
        try{
            if(@isset($list[$name])) return true;
        }catch(Exception $e){
            return false;
        }
        
        return false;
    }
    
    public function makelist($data,$sender){
        $sender->sendMessage("プラグインリストを生成中…");
        $cache = [];
        foreach($data as $value){
            foreach($value as $package){
                //var_dump($package);
                $error = false;
                if(!isset($package["name"])){
                    $sender->sendMessage("エラー(必須のパラメーター[name]が設定されていません)");
                    $sender->sendMessage("アップデート処理に失敗しました");
                    return True;
                }
                $cache[$package["name"]]["name"] = $package["name"];
                if(!isset($package["version"])){
                    //$sender->sendMessage("エラー(必須のパラメーター[version]が設定されていません):".$package["name"]);
                    $error = true;
                }
                $cache[$package["name"]]["version"] = $package["version"];
               if(!isset($package["artifact_url"])){
                    //$sender->sendMessage("エラー(必須のパラメーター[artifact_url]が設定されていません):".$package["name"]);
                    $error = true;
                }
                $cache[$package["name"]]["artifact_url"] = $package["artifact_url"];
                if(!isset($package["api"])||!isset($package["api"][0])||!isset($package["api"][0]["from"])||!isset($package["api"][0]["to"])){
                    //$sender->sendMessage("エラー(必須のパラメーター[api]が設定されていないか、不正です。):".$package["name"]);
                    $error = true;
                }
                if(!empty($package["api"])){
                    $cache[$package["name"]]["api"] = $package["api"][0];
                }
                if(!isset($package["deps"])){
                    //$sender->sendMessage("エラー(必須のパラメーター[deps]が設定されていません):".$package["name"]);
                    $error = true;
                }
                $cache[$package["name"]]["deps"] = $package["deps"];
                
                if(isset($package["mcpe-protocol"])){
                    $cache[$package["name"]]["mcpe-protocol"] = $package["mcpe-protocol"];
                }
                /*
                if($error){
                    unset($cache[$package["name"]]);
                    $cache = array_values($cache);
                }*/
            }
        }
        $this->checkdepsinlist($cache,$sender);
    }
    
    public function checkdepsinlist($data,$sender){
        $sender->sendMessage("依存関係を確認中です");
        foreach($data as $value){
            foreach($value["deps"] as $dep){
                if(!isset($dep)) continue;
                if(!isset($dep["name"])){
                    $sender->sendMessage("エラー(".$value["name"]."の依存プラグインの項目が不正です");
                    $sender->sendMessage("アップデート作業に失敗しました");
                    return True;
                }
                if(!$this->checkplugininlist($data,$dep["name"])){
                    $sender->sendMessage("エラー:".$value["name"]."の依存プラグイン(".$dep["name"].")がプラグインリストに見つかりません。");
                    //$sender->sendMessage("アップデート作業に失敗しました");
                    //return True;
                }
            }
        }
        $sender->sendMessage("依存関係の確認が終了しました");
        $sender->sendMessage("データを記録中です");
        $this->packagelist->set("list",$data);
        $this->packagelist->save();
        $this->packagelist->reload();
        $sender->sendMessage("データの記録が完了しました");
        $sender->sendMessage("アップデート作業が完了しました");
    }
}