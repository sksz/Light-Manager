# Light Manager — jedno wejście do procesów projektu.
#
# Pełny spis „proces → wejście” wraz z regułą pierwszeństwa narzędzi
# repozytorium: docs/architecture.md, rozdz. 8 „Procesy projektu”.
# Rozstrzygnięcia, na których stoi ten plik: docs/plans/00-decyzje.md, D72.
#
# Zasada, od której zależy, czy plik zestarzeje się dobrze: **cel nie jest
# drugim źródłem prawdy**. Cele jakości wołają skrypty Composera, pomiar woła
# `bin/render-bench`, uruchomienie pod XTermem woła `bin/run.sh` — Makefile
# niczego z tego nie powtarza własnymi słowami.

SHELL := /bin/sh
.DEFAULT_GOAL := help

# Bramka jakości ma się przewracać w znanej kolejności, a nie w tej, którą
# akurat wybierze `make -j`. Cele są cienkie, więc równoległość nic tu nie daje.
.NOTPARALLEL:

PHP      ?= php
COMPOSER ?= composer

AUTOLOAD  := vendor/autoload.php
BUILD_DIR := build
VERSION   := $(shell $(PHP) -r 'echo json_decode(file_get_contents("composer.json"), true)["version"] ?? "0.0.0";' 2>/dev/null)
PHAR      := $(BUILD_DIR)/light-manager-$(VERSION).phar

# Argumenty przekazywane do narzędzi repozytorium, np.:
#   make bench ARGS='--palette=16 --save'
#   make test  ARGS='--filter TreeStateTest'
ARGS ?=

# Katalog `conf.d` bez rozszerzenia `imagick` — dla `make install-safe`.
COMPOSER_INI_SCAN_DIR ?=

.PHONY: help check-env install install-safe cs cs-check stan qa qa-full \
        test test-unit test-functional coverage \
        bench bench-window bench-text bench-loop bench-xterm \
        run run-window run-xterm probe probe-xterm \
        build clean dist-clean

##@ Pomoc

help: ## Wypisuje ten spis
	@printf 'Light Manager %s — wejścia do procesów projektu.\n' '$(VERSION)'
	@printf 'Pełny opis: docs/architecture.md, rozdz. 8 „Procesy projektu”.\n'
	@awk 'BEGIN {FS = ":.*##"} \
		/^##@/ {printf "\n%s\n", substr($$0, 5)} \
		/^[a-zA-Z0-9_-]+:.*##/ {printf "  %-16s %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@printf '\nArgumenty do narzędzi: ARGS='"'"'…'"'"' (np. make bench ARGS='"'"'--save'"'"').\n'

##@ Środowisko i instalacja

check-env: ## Sprawdza wymagania środowiska (działa przed instalacją zależności)
	@hard=0; \
	say() { printf '  [%-4s] %s\n' "$$1" "$$2"; }; \
	printf 'Light Manager — environment check\n\nRequired:\n'; \
	if command -v $(PHP) >/dev/null 2>&1; then \
		if $(PHP) -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then \
			say ok "PHP $$($(PHP) -r 'echo PHP_VERSION;') (>= 8.3)"; \
		else \
			say FAIL "PHP $$($(PHP) -r 'echo PHP_VERSION;') — 8.3 or newer is required"; hard=1; \
		fi; \
		for ext in imagick pcntl; do \
			if $(PHP) -r "exit(extension_loaded('$$ext') ? 0 : 1);"; then \
				say ok "ext-$$ext"; \
			else \
				say FAIL "ext-$$ext is missing"; hard=1; \
			fi; \
		done; \
	else \
		say FAIL "PHP not found in PATH — 8.3 or newer is required"; hard=1; \
		say FAIL "ext-imagick — cannot be checked without PHP"; \
		say FAIL "ext-pcntl — cannot be checked without PHP"; \
	fi; \
	if command -v stty >/dev/null 2>&1; then \
		say ok "stty (raw terminal mode)"; \
	else \
		say FAIL "stty is missing — the terminal cannot be switched to raw mode"; hard=1; \
	fi; \
	if command -v $(COMPOSER) >/dev/null 2>&1; then \
		say ok "Composer $$($(COMPOSER) --version --no-ansi 2>/dev/null | sed -n 's/^Composer version \([^ ]*\).*/\1/p') (needed by: make install, make build)"; \
	else \
		say FAIL "Composer 2.x is missing — needed by: make install, make build"; hard=1; \
	fi; \
	printf '\nRecommended:\n'; \
	if command -v $(PHP) >/dev/null 2>&1 && $(PHP) -r "exit(extension_loaded('imagick') && in_array('SIXEL', (new Imagick())->queryFormats('SIXEL'), true) ? 0 : 1);" 2>/dev/null; then \
		say ok "ImageMagick SIXEL coder"; \
	else \
		say warn "ImageMagick SIXEL coder is missing — the app starts and falls back to text mode"; \
	fi; \
	printf '\nOptional:\n'; \
	if command -v $(PHP) >/dev/null 2>&1 && $(PHP) -r "exit(extension_loaded('glfw') ? 0 : 1);"; then \
		say ok "ext-glfw — windowed mode (--window) and the audio module"; \
	else \
		say '--' "ext-glfw — without it --window refuses to start; terminal modes are unaffected"; \
	fi; \
	if command -v $(PHP) >/dev/null 2>&1 && $(PHP) -r "exit(extension_loaded('intl') ? 0 : 1);"; then \
		say ok "ext-intl — locale-aware sorting and number formatting"; \
	else \
		say '--' "ext-intl — without it sorting and number formatting degrade"; \
	fi; \
	if command -v xterm >/dev/null 2>&1; then \
		say ok "xterm — make run-xterm, make bench-xterm (--transfer)"; \
	else \
		say '--' "xterm — without it make run-xterm and make bench-xterm do not work"; \
	fi; \
	printf '\nNot checkable here:\n'; \
	printf '  Sixel support of the terminal (DA1 reply) needs an interactive terminal\n'; \
	printf '  in raw mode, which make does not have. Run: make probe\n\n'; \
	if [ "$$hard" -ne 0 ]; then \
		printf 'Environment is NOT ready — see [FAIL] above.\n'; exit 1; \
	fi; \
	printf 'Environment is ready.\n'

install: check-env $(AUTOLOAD) ## Instaluje zależności Composera (nie robi nic, gdy są aktualne)

# Cel plikowy, nie `.PHONY`: powtórzona instalacja nie robi nic, a cele jakości
# i testów mogą go wskazać jako zależność i działać na świeżym klonie.
$(AUTOLOAD): composer.json composer.lock
	$(COMPOSER) install
	@touch $@

install-safe: check-env ## Instalacja z obejściem SIGSEGV Composera (COMPOSER_INI_SCAN_DIR=/ścieżka/do/conf.d-bez-imagick)
	@if [ -z '$(COMPOSER_INI_SCAN_DIR)' ]; then \
		printf 'Podaj katalog conf.d bez rozszerzenia imagick, np.:\n'; \
		printf '  make install-safe COMPOSER_INI_SCAN_DIR=/etc/php/8.3/cli/conf.d-bez-imagick\n'; \
		printf 'Powód obejścia: README, „Znane ograniczenie środowiska”.\n'; \
		exit 1; \
	fi
	PHP_INI_SCAN_DIR='$(COMPOSER_INI_SCAN_DIR)' $(COMPOSER) install --ignore-platform-req=ext-imagick
	@touch $(AUTOLOAD)

##@ Jakość kodu

cs: $(AUTOLOAD) ## PHP-CS-Fixer — zapisuje poprawki
	$(COMPOSER) cs

cs-check: $(AUTOLOAD) ## PHP-CS-Fixer — podgląd zmian, bez zapisu
	$(COMPOSER) cs:check

stan: $(AUTOLOAD) ## PHPStan (poziom max)
	$(COMPOSER) stan

qa: cs-check stan test ## Bramka jakości: cs-check → stan → test, stop na pierwszym błędzie
	@printf '\nBramka przeszła: cs-check, stan, test.\n'

qa-full: $(AUTOLOAD) ## To samo, ale do końca — ze zbiorczym podsumowaniem
	@fail=0; cs=OK; stan=OK; test=OK; \
	$(COMPOSER) cs:check || { cs=BŁĄD; fail=1; }; \
	$(COMPOSER) stan     || { stan=BŁĄD; fail=1; }; \
	$(COMPOSER) test     || { test=BŁĄD; fail=1; }; \
	printf '\nPodsumowanie bramki:\n'; \
	printf '  cs-check  %s\n  stan      %s\n  test      %s\n' "$$cs" "$$stan" "$$test"; \
	exit $$fail

##@ Testy

test: $(AUTOLOAD) ## PHPUnit — obie grupy naraz
	$(COMPOSER) test -- $(ARGS)

test-unit: $(AUTOLOAD) ## PHPUnit — grupa `unit` (klasy)
	$(COMPOSER) test -- --testsuite unit $(ARGS)

test-functional: $(AUTOLOAD) ## PHPUnit — grupa `functional` (przebiegi użytkownika)
	$(COMPOSER) test -- --testsuite functional $(ARGS)

coverage: $(AUTOLOAD) ## Pokrycie testami do build/coverage/ (wymaga Xdebuga albo PCOV-u)
	@if ! $(PHP) -r "exit(extension_loaded('xdebug') || extension_loaded('pcov') ? 0 : 1);"; then \
		printf 'Pokrycia nie ma czym policzyć: brakuje rozszerzenia Xdebug albo PCOV.\n'; \
		printf 'Żadne z nich nie jest wymaganiem projektu — zainstaluj jedno z:\n'; \
		printf '  pecl install pcov      (szybsze, liczy wyłącznie pokrycie)\n'; \
		printf '  pecl install xdebug    (wolniejsze, za to z debuggerem)\n'; \
		exit 1; \
	fi
	XDEBUG_MODE=coverage $(COMPOSER) test -- --coverage-html $(BUILD_DIR)/coverage $(ARGS)
	@printf '\nRaport: $(BUILD_DIR)/coverage/index.html\n'

##@ Pomiar wydajności (poza bramką jakości — krok 16)

bench: $(AUTOLOAD) ## Pomiar toru sixelowego (bin/render-bench)
	@printf 'Pomiar wymaga spokojnej maszyny: zatrzymaj kompilacje, kontenery\n'
	@printf 'i przeglądarkę. Obciążony host daje rozrzut, przy którym --save\n'
	@printf 'odmawia zapisu wzorca. Zobacz CLAUDE.md.\n\n'
	./bin/render-bench $(ARGS)

bench-window: $(AUTOLOAD) ## Pomiar toru okienkowego (OpenGL, okno ukryte)
	$(MAKE) bench ARGS='--window $(ARGS)'

bench-text: $(AUTOLOAD) ## Pomiar toru tekstowego (ANSI, tryb zapasowy)
	$(MAKE) bench ARGS='--text $(ARGS)'

bench-loop: $(AUTOLOAD) ## Pomiar taktu pętli (wejście → stan → złożenie klatki)
	$(MAKE) bench ARGS='--loop $(ARGS)'

bench-xterm: $(AUTOLOAD) ## Pomiar pod prawdziwym XTermem — jedyna droga do --transfer
	@printf 'Pomiar wymaga spokojnej maszyny — zobacz CLAUDE.md.\n\n'
	./bin/run-render-bench.sh $(ARGS)

##@ Uruchomienie

run: $(AUTOLOAD) ## Uruchamia aplikację w bieżącym terminalu
	./bin/light-manager $(ARGS)

run-window: $(AUTOLOAD) ## Uruchamia aplikację w oknie GLFW (--window)
	./bin/light-manager --window $(ARGS)

run-xterm: $(AUTOLOAD) ## Uruchamia aplikację w XTermie z zasobami trybu graficznego
	./bin/run.sh

probe: $(AUTOLOAD) ## Podgląd wejścia terminala (bin/terminal-probe) — także odpowiedź DA1
	./bin/terminal-probe

probe-xterm: $(AUTOLOAD) ## Podgląd wejścia w XTermie z zasobami trybu graficznego
	./bin/run-terminal-probe.sh

##@ Budowa i sprzątanie

build: check-env $(AUTOLOAD) ## Buduje build/light-manager-<wersja>.phar wraz z assets/ obok archiwum
	$(PHP) -d phar.readonly=0 bin/build-phar

clean: ## Usuwa wytwory narzędzi i katalog budowy
	rm -rf $(BUILD_DIR)
	rm -f .php-cs-fixer.cache .phpunit.result.cache

dist-clean: clean ## To samo plus vendor/
	rm -rf vendor
