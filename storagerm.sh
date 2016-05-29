#!/bin/bash
sleep 2
if mv "$1" "$1".2rm;then
	wget -S --spider "$2" && rm "$1".2rm || mv "$1".2rm "$1"
fi
