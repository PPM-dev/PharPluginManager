<?php
$data = [];
$result = file_get_contents("https://poggit.pmmp.io/plugins.json?latest-only");
$result = json_decode($result,true);
$i = 0;
foreach ($result[0] as $value) {
    $data[$i]["name"] = $value["name"];
    $data[$i]["version"] = $value["version"];
    $data[$i]["artifact_url"] = $value["artifact_url"];
    $data[$i]["api"] = $value["api"];
    $data[$i]["deps"] = $value["deps"];    
    $i = $i+1;
}
file_put_contents("Database.json",json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
echo "success";
