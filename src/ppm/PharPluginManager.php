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


class PharPluginManager extends PluginBase implements Listener
{
    public $source;
    public $packagelist;
    public $plugin;
    public $num;

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
                switch($args[0]){
                    case "install":
                        if(!isset($args[1])){
                            $sender->sendMessage("プラグイン名を指定してください");
                            return True;
                        }
                        
                        $sender->sendMessage("指定されたプラグインを検索中です");
                        $list = $this->packagelist->get("list");
                        $this->num = 0;
                        if(!$this->checkplugininlist($list,$args[1])){
                            $sender->sendMessage("プラグインが見つかりませんでした");
                            $sender->sendMessage("入力値を確認するか、/ppm updateを実行してください");
                            return True;
                        }
                        $sender->sendMessage("プラグインが現在使用中のバージョンで使用できるか確認しています。");
                        if(!$this->checkAPIversion($args[1],$this->getServer()->getApiVersion())){
                            $sender->sendMessage("指定されたプラグインは現在使用中のバージョンには対応していません");
                            $sender->sendMessage("30分後くらいに/ppm updateを実行してみてください");
                            $sender->sendMessage("それでも改善しない場合はレポジトリの管理者に問い合わせてください");
                            return True;
                        }
                        
                        $sender->sendMessage("プラグインのダウンロードを開始します");
                        $options = stream_context_create(array('ssl' => array(
                          'verify_peer'      => false,
                          'verify_peer_name' => false
                        )));
                        
                        $result = @file_get_contents($list[0][$this->num]["artifact_url"], false, $options);
                        if(!$result){
                            $sender->sendMessage("エラー:ダウンロードに失敗しました");
                            $sender->sendMessage("サーバに接続できないか、サーバーからエラーが返されました");
                            $sender->sendMessage("プラグインのインストールに失敗しました");
                            return True;
                        }
                        $sender->sendMessage("プラグインの依存関係を確認しています");
                        foreach($list[0][$this->num]["deps"] as $dep){
                            $options = stream_context_create(array('ssl' => array(
                                    'verify_peer'      => false,
                                    'verify_peer_name' => false
                                )));
                            
                            $this->num = 0;
                            foreach($list as $value){
                                foreach($value as $package){
                                    $this->num = $this->num + 1;
                                    if($package["name"] == $dep["name"]) return true;
                                }
                            }
                            $result = @file_get_contents($list[0][$this->num]["artifact_url"], false, $options);
                            if(!$result){
                                $sender->sendMessage("エラー:依存関係のダウンロードに失敗しました");
                                $sender->sendMessage("サーバに接続できないか、サーバーからエラーが返されました");
                                $sender->sendMessage("プラグインのインストールに失敗しました");
                                return True;
                            }
                        }
                        
                        $sender->sendMessage("プラグインを保存しています");
                        @file_put_contents($this->getDataFolder()."plugins/".$args[1].".phar",$result);
                        $sender->sendMessage("保存完了しました");
                        $sender->sendMessage("プラグインを有効化するにはサーバーを再起動してください");
                        break;
                    case "uninstall":
                        if(!isset($args[1])){
                            $sender->sendMessage("プラグイン名を指定してください");
                            return True;
                        }
                        $sender->sendMessage("インストールされているプラグインを検索中です");
                        $result = glob($this->getDataFolder()."plugins/*.phar");
                        if(!in_array($args[1].".phar", $result)){
                            $sender->sendMessage("そのプラグインは現在インストールされていないようです");
                            return True;
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
                        }
                        $this->makelist($cache,$sender);
                        break;
                    case "upgrade":
                        if(!isset($args[1])){
                            $sender->sendMessage("プラグイン名を指定してください");
                            return True;
                        }
                        $sender->sendMessage("インストールされているプラグインを検索中です");
                        $result = glob($this->getDataFolder()."plugins/*.phar");
                        if(!in_array($args[1].".phar", $result)){
                            $sender->sendMessage("そのプラグインは現在インストールされていないようです");
                            return True;
                        }
                        $sender->sendMessage("プラグインが見つかりました");
                        $sender->sendMessage("指定されたプラグインを検索中です");
                        $list = $this->packagelist->get("list");
                        $this->num = 0;
                        if(!$this->checkplugininlist($list,$args[1])){
                            $sender->sendMessage("プラグインが見つかりませんでした");
                            $sender->sendMessage("入力値を確認するか、/ppm updateを実行してください");
                            return True;
                        }
                        $sender->sendMessage("プラグインが現在使用中のバージョンで使用できるか確認しています。");
                        if(!$this->checkAPIversion($args[1],$this->getServer()->getApiVersion())){
                            $sender->sendMessage("指定されたプラグインは現在使用中のバージョンには対応していません");
                            $sender->sendMessage("30分後に/ppm updateを実行してみてください");
                            $sender->sendMessage("それでも改善しない場合はレポジトリの管理者に問い合わせてください");
                            return True;
                        }
                        $sender->sendMessage("プラグインのダウンロードを開始します");
                        $options = stream_context_create(array('ssl' => array(
                            'verify_peer'      => false,
                            'verify_peer_name' => false
                        )));
                                                
                        $result = @file_get_contents($list[0][$this->num]["artifact_url"], false, $options);
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
                            return True;
                        }
                        $sender->sendMessage("レポジトリの存在確認を行います");
                        $options = stream_context_create(array('ssl' => array(
                          'verify_peer'      => false,
                          'verify_peer_name' => false
                        )));
                        
                        $result = @file_get_contents($args[1]."Repo.json", false, $options);
                        preg_match("/[0-9]{3}/", $http_response_header[0], $stcode);
                        if($stcode!==200){
                            $sender->sendMessage("URL:".$args[1]."Repo.json\nは200以外のステータスコードを返しました");
                            $sender->sendMessage("レポジトリの管理者にお問合せください");
                            return True;
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
                            return True;
                        }
                        $sender->sendMessage("登録されているレポジトリを検索中です");
                        $repoarray = $this->source->get("repo");
                        if(!in_array($args[1]."Repo.json",$repoarray)){
                            $sender->sendMessage("指定されたレポジトリは登録されていないようです");
                            return True;
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
                        
                        break;
                        
                }
                break;
        }
        return true;
    }
    
    public function checkAPIversion($name,$api){
        $list = $this->packagelist->get("list");
        $apiversion = $list[0][$this->num]["api"];
        $from = $apiversion["from"];
        $to = $apiversion["to"];
        $from_split = explode(".",$from);
        $to_split = explode(".",$to);
        $api_split = explode(".",$api);
        if($from_split[0] > $api_split[0]) return false;
        if($from_split[1] > $api_split[1]) return false;
        if($from_split[2] > $api_split[2]) return false;
        if($to_split[0] < $api_split[0]) return false;
        if($to_split[1] < $api_split[1]) return false;
        if($to_split[2] < $api_split[2]) return false;
        return true;
    }
    
    public function checkplugininlist($list,$name){
        foreach($list as $value){
            foreach($value as $package){
                $this->num = $this->num + 1;
                if($package["name"] == $name) return true;
            }
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
                
                if($error){
                    unset($cache[$package["name"]]);
                    $cache = array_values($cache);
                }
            }
        }
        //$this->checkdepsinlist($cache,$sender);
        $sender->sendMessage("データを記録中です");
        $this->packagelist->set("list",$data);
        $this->packagelist->save();
        $this->packagelist->reload();
        $sender->sendMessage("データの記録が完了しました");
        $sender->sendMessage("アップデート作業が完了しました");
    }
    
    public function checkdepsinlist($data,$sender){
        $sender->sendMessage("依存関係を確認中です");
        foreach($data as $value){
            var_dump($value);
            foreach($value["deps"] as $dep){
                if(!isset($dep)) continue;
                if(!isset($dep["name"])){
                    $sender->sendMessage("エラー(".$value["name"]."の依存プラグインの項目が不正です");
                    //$sender->sendMessage("アップデート作業に失敗しました");
                    //return True;
                }
                if(!$this->checkplugininlist($data,$dep["name"])){
                    $sender->sendMessage("エラー(".$value["name"]."の依存プラグインがプラグインリストに見つかりません。");
                    //$sender->sendMessage("アップデート作業に失敗しました");
                    //return True;
                }
            }
        }
        $sender->sendMessage("依存関係の確認が終了しました");
    }
}