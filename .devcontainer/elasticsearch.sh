#!/bin/sh
curl -X PUT -H "Content-Type: application/json" -d @.devcontainer/elasticsearch.json http://elasticsearch:9200/_template/halon