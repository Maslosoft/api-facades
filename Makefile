# Makefile for dev operations
SHELL := /usr/bin/env bash
.SHELLFLAGS := -eu -o pipefail -c
MAKEFLAGS += --no-builtin-rules --warn-undefined-variables
.PHONY: help unit

# Binaries def
PHP ?= php8.4
CODECEPT ?= vendor/bin/codecept

.DEFAULT_GOAL := help

help:
	@printf "%s\n" "Commands:" \
	"  make unit <Name>                # Generate codeception unit test" \
	"  make test                       # Run all tests"


test: ## make tests
	$(PHP) vendor/bin/codecept run --debug

unit: ## make unit <Name>
	@name="$(strip $(firstword $(filter-out $@,$(MAKECMDGOALS))))"; \
	test -n "$$name" || { echo "Usage: make unit <Name>"; exit 2; }; \
	$(PHP) $(CODECEPT) generate:test Unit "$$name"

# Swallow extra goal words so "make unit MyTest" doesn't error on "MyTest"
%:
	@:
