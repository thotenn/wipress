# ============================================================
# WiPress — command index
# ------------------------------------------------------------
# Single entry point for the project's dev/release scripts under
# scripts/. Keeps commands easy to inventory and document as more
# scripts are added.
#
# Convention:
#   - Scripts live in scripts/; each is wired here as a target that
#     only invokes it (the Makefile routes, it does not duplicate logic).
#   - Add a `## <description>` after each target so it shows up in
#     `make help` automatically.
#
# Usage:
#   make            # same as `make help`
#   make help       # list every command
#   make build      # build the distributable plugin zip
# ============================================================

SCRIPTS := scripts

.DEFAULT_GOAL := help

.PHONY: help build

help: ## Show this help (all commands)
	@awk 'BEGIN {FS = ":.*##"} \
		/^##@/ {printf "\n\033[1m%s\033[0m\n", substr($$0, 5); next} \
		/^[a-zA-Z0-9_.-]+:.*?##/ {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}' \
		$(MAKEFILE_LIST)
	@echo ""

build: ## Build the distributable plugin zip (wipress-<version>.zip)
	@bash $(SCRIPTS)/build.sh
