<?php
$ignored = array("host", "x-forwarded-for", "x-real-ip");
$method = $_SERVER['REQUEST_METHOD'];
$queries = array();
$headers = array();
$body;

//Parse query string
parse_str($_SERVER['QUERY_STRING'], $queries);

//If no URL query parameter was provided, exit
if (!array_key_exists("url", $queries))
{
    http_response_code("400");
    echo "No query parameter provided.";
    return;
}

$url = $queries["url"];

//If the request is post, load the input body
if ($method == "POST")
{
    $body = file_get_contents("php://input");
}

//Enumerate through all HTTP headers and save them into the Headers array
foreach($_SERVER as $key => $value)
{
    if (substr($key, 0, 5) <> 'HTTP_')
    {
        continue;
    }
    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));

    if (in_array(strtolower($header), $ignored)) continue;
      
    $headers[] = $header . ": " . $value;
}

//Finally, create a CURL request and load all the proxied information
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);   
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_HEADERFUNCTION, "handleHeaderLine");
function handleHeaderLine($curl, $header_line) { return strlen($header_line); }
curl_setopt($ch, CURLOPT_ENCODING, "UTF-8");

//If files were uploaded, enumerate through them and add them into the request
if (!empty($_FILES))
{
    curl_setopt($ch, CURLOPT_POST, 1);
    $uploadedFiles = array_keys($_FILES);
    $postData = array();
    foreach ($uploadedFiles as $uploaded)
    {
        foreach($_FILES[$uploaded] as $key => $value )
        {
            if ($key == "name") $fileName = $value;
            else if ($key == "type") $fileMime = $value;
            else if ($key == "tmp_name") $filePath = $value;
            else if ($key == "size") $fileSize = $value;
        }
        
        curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        
        $fileData = curl_file_create($filePath, $fileMime, $fileName);
        $postData[$uploaded] = $fileData;
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
}
//If files weren't uploaded, but there is some body, upload it
else if ($method == "POST")
{
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

//Execute the request and proxy it back
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

http_response_code(intval($status));
echo $res;

curl_close($ch);
?>
