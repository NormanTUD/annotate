#!/bin/bash

HOST=$1
PORT=$2

curl "http://$HOST:$PORT/upload_model.php" \
  -X POST \
  -H 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0' \
  -H 'Accept: */*' \
  -H 'Accept-Language: en-US,en;q=0.5' \
  -H "Referer: http://$HOST:$PORT/models.php" \
  -H "Origin: http://$HOST:$PORT" \
  -H 'DNT: 1' \
  -H 'Connection: keep-alive' \
  -H 'Cookie: language_cookie=de; PHPSESSID=eipc9q7sc1rg3ul754et4kfeet' \
  -F "model_name=my_test_model" \
  -F "pt_model_file=@last.pt;filename=last.pt"
