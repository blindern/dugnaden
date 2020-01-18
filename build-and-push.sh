#!/bin/bash
set -eux

tag="$(date -u +%Y%m%d-%H%M%S)"
image="blindern/dugnaden:$tag"

docker build --pull -t "$image" .
docker push "$image"
