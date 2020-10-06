<?php

define('PROTOCOL', "https");

function query($method, $url, $data = null)
{
	$query_data = "";

	$curlOptions = array(
		CURLOPT_RETURNTRANSFER => true
	);

	if($method == "POST")
	{
		$curlOptions[CURLOPT_POST] = true;
		$curlOptions[CURLOPT_POSTFIELDS] = http_build_query($data);
	}
	elseif(!empty($data))
	{
		$url .= strpos($url, "?") > 0 ? "&" : "?";
		$url .= http_build_query($data);
	}

	$curl = curl_init($url);
	curl_setopt_array($curl, $curlOptions);
	$result = curl_exec($curl);

	return json_decode($result, 1);
}

function call($domain, $method, $params)
{
	return query("POST", PROTOCOL."://".$domain."/rest/".$method, $params);
}

// Получаем из массива Id задачи, токен и домен
$id = $_REQUEST['data']['FIELDS_AFTER']['ID'];
$auth = $_REQUEST['auth']['access_token'];
$domain = $_REQUEST['auth']['domain'];

//Получем данные связанных c задачей сущностей CRM
$data = call($domain, "tasks.task.get", array(
				"auth" => $auth,
				"taskId" => intval($id),
				"select" => array('UF_CRM_TASK'),
			));
$clientCrm = $data['result']['task']['ufCrmTask'][0];

if (substr($clientCrm, 0,2) == "C_"){ //Проверяем по префиксу, что привязан именно контакт (в данном случае подрозумеваем, что привязана только одна сущность, если несколько, то надо перебрать массив в цикле)
    $clientId = substr($clientCrm, 2); // Получаем ID контакта
    
    $data2 = call($domain, "crm.deal.list", array(
            "auth" => $auth,
            "order" => array("ID" => "DESC"),
            "filter" => array("CONTACT_ID" => $clientId),
            "select" => array('ID'),
        ));    
    $dealId = $data2["result"][0]["ID"]; //Получаем последнюю сделку контакта
    
    if ($dealId != ""){// если хоть одна сделка существует
        $dealIdStr = "D_".$dealId;//Добавляем префикс для записи в задачу
        
        $data3 = call($domain, "task.item.update", array(//Обновляем задачу, добавляя новую связку с CRM
                "auth" => $auth,
                "TASKID" => intval($id),
                "TASKDATA" => array('UF_CRM_TASK' => array($clientCrm, $dealIdStr),
            )));
    }    
}

?>