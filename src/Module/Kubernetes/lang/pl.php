<?php

declare(strict_types=1);

/*
 * Napisy modułu „Kubernetes” — polski.
 *
 * **Każdy klucz musi zaczynać się od `module.k8s.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę.
 *
 * Trzy rodzaje napisów świadomie **nie mają tu kluczy**, bo nie są napisami tej
 * aplikacji. Pierwszy: powody odmowy od `kubectl` (cytat z cudzego programu,
 * wstawiany w `{reason}`) — ta sama granica, co przy `sftp` w kroku 49
 * i demonie Dockera w 51. Drugi: **nazwy rodzajów zasobów** (`pods`,
 * `deployments`) — pochodzą z klastra, jest ich kilkadziesiąt i przybywa ich
 * z każdym operatorem, a przetłumaczony „pod” przestałby dać się wpisać
 * w `kubectl`. Trzeci: **nazwy kolumn własnych rodzaju** w postaci, w jakiej
 * zna je Kubernetes — te akurat klucze mają, ale ich treść jest cytatem
 * z narzędzia (`READY`, `STATUS`), bo użytkownik porównuje ekran z terminalem
 * obok.
 */

return [
    'module.k8s.name' => 'Kubernetes',
    'module.k8s.description' => 'Zasoby klastra w drzewie, opis w sekcjach, logi na żywo, apply i edycja sekretów.',
    'module.k8s.unavailable.client' => 'Brak polecenia kubectl w PATH.',

    // Nazwy, które nie nadają się na argument polecenia.
    'module.k8s.name.empty' => 'Pusta nazwa ({subject}).',
    'module.k8s.name.optionLike' => 'Nazwa „{value}” zaczyna się od myślnika — kubectl wziąłby ją za opcję.',
    'module.k8s.name.malformed' => 'Nazwa „{value}” zawiera znaki, których w niej być nie może ({subject}).',
    'module.k8s.name.tooLong' => 'Nazwa „{value}” jest dłuższa niż {limit} znaków.',

    // Ustawienia modułu.
    'module.k8s.setting.timeoutSeconds' => 'Limit czasu wywołania (s)',
    'module.k8s.setting.refreshSeconds' => 'Odświeżanie listy (s)',
    'module.k8s.setting.logLines' => 'Wierszy logu w pamięci',

    // Stany rozmowy z klastrem.
    'module.k8s.stage.unknown' => 'Klaster jeszcze niepytany.',
    'module.k8s.stage.reading' => 'Pytam klaster…',
    'module.k8s.stage.noContext' => 'Żaden klaster nie jest wybrany — naciśnij c i wybierz go ze spisu.',
    'module.k8s.stage.unreachable' => 'Klaster nie odpowiada.',
    'module.k8s.stage.ready' => 'Klaster odpowiada.',

    // Powody niepowodzeń.
    'module.k8s.problem.config' => 'Nie udało się odczytać pliku konfiguracyjnego kubectl.',
    'module.k8s.problem.unreachable' => 'Klaster nie odpowiada: {reason}',
    'module.k8s.problem.catalog' => 'Nie udało się pobrać spisu rodzajów zasobów.',
    'module.k8s.problem.list' => 'Nie udało się pobrać listy zasobów.',
    'module.k8s.problem.rejected' => 'kubectl odmówił: {reason}',
    'module.k8s.problem.detail' => 'Nie udało się pobrać opisu zasobu.',
    'module.k8s.problem.action' => 'Czynność się nie powiodła.',

    // Nagłówek i wersje.
    'module.k8s.header.place' => 'Kontekst {context}, przestrzeń {namespace}',
    'module.k8s.header.noPlace' => 'Brak wybranego klastra',
    'module.k8s.version.skew' => '— uwaga: klient {client}, serwer {server}',

    // Panele i postacie ekranu.
    'module.k8s.panel.tree' => 'Zasoby',
    'module.k8s.panel.content' => 'Treść',
    'module.k8s.panel.logs' => 'Logi',
    'module.k8s.view.resources' => 'Klaster',
    'module.k8s.view.yaml' => 'Klaster — YAML',
    'module.k8s.view.logs' => 'Klaster — logi',

    // Drzewo i listy.
    'module.k8s.tree.reading' => 'czytam…',
    'module.k8s.tree.empty' => '(pusto)',
    'module.k8s.tree.none' => 'Brak rodzajów zasobów — klaster nie podał spisu.',
    'module.k8s.list.reading' => 'Czytam listę: {kind}…',
    'module.k8s.list.empty' => 'Brak zasobów rodzaju {kind} w tej przestrzeni nazw.',
    'module.k8s.content.none' => 'Wybierz rodzaj albo zasób w drzewie po lewej.',
    'module.k8s.content.group' => 'Grupa API — rozwiń ją, żeby zobaczyć rodzaje zasobów.',

    // Kolumny list. Treść jest cytatem z kubectl — patrz nagłówek pliku.
    'module.k8s.column.name' => 'Nazwa',
    'module.k8s.column.age' => 'Wiek',
    'module.k8s.column.ready' => 'READY',
    'module.k8s.column.status' => 'STATUS',
    'module.k8s.column.restarts' => 'RESTARTY',
    'module.k8s.column.node' => 'WĘZEŁ',
    'module.k8s.column.upToDate' => 'AKTUALNE',
    'module.k8s.column.available' => 'DOSTĘPNE',
    'module.k8s.column.type' => 'TYP',
    'module.k8s.column.clusterIp' => 'CLUSTER-IP',
    'module.k8s.column.ports' => 'PORTY',
    'module.k8s.column.data' => 'KLUCZY',

    // Opis zasobu.
    'module.k8s.detail.none' => 'Nie wybrano zasobu.',
    'module.k8s.detail.reading' => 'Czytam zasób…',
    'module.k8s.detail.missing' => 'Nie udało się odczytać zasobu.',
    'module.k8s.detail.rejected' => 'Nazwa „{name}” nie nadaje się na argument polecenia.',
    'module.k8s.detail.name' => 'Nazwa',
    'module.k8s.detail.kind' => 'Rodzaj',
    'module.k8s.detail.apiVersion' => 'Wersja API',
    'module.k8s.detail.namespace' => 'Przestrzeń nazw',
    'module.k8s.detail.created' => 'Utworzono',
    'module.k8s.detail.phase' => 'Faza',
    'module.k8s.detail.section.identity' => 'Tożsamość',
    'module.k8s.detail.section.status' => 'Stan',
    'module.k8s.detail.section.containers' => 'Kontenery',
    'module.k8s.detail.section.data' => 'Dane',
    'module.k8s.detail.section.labels' => 'Etykiety',
    'module.k8s.detail.section.annotations' => 'Adnotacje',
    'module.k8s.detail.bytes' => ['{count} bajt', '{count} bajty', '{count} bajtów'],

    // Logi.
    'module.k8s.logs.header' => 'Logi: {name} / {container}',
    'module.k8s.logs.container' => 'Z którego kontenera czytać logi?',
    'module.k8s.logs.cancel' => 'Nie czytaj',
    'module.k8s.logs.waiting' => 'Czekam na pierwszy wiersz…',
    'module.k8s.logs.closed' => 'Strumień zamknięty.',
    'module.k8s.logs.broken' => 'Strumień się urwał — pod mógł zniknąć.',
    'module.k8s.logs.failed' => 'Nie udało się otworzyć logów.',
    'module.k8s.logs.interrupted' => 'Strumień logów został przerwany.',
    'module.k8s.logs.notAPod' => 'Logi ma pod — wybierz go w drzewie.',
    'module.k8s.logs.lost' => ['Pominięto {count} bajt', 'Pominięto {count} bajty', 'Pominięto {count} bajtów'],

    // Wiek zasobu.
    'module.k8s.age.minutes' => ['{count} min', '{count} min', '{count} min'],
    'module.k8s.age.hours' => ['{count} godz.', '{count} godz.', '{count} godz.'],
    'module.k8s.age.days' => ['{count} dzień', '{count} dni', '{count} dni'],

    // Wybór miejsca.
    'module.k8s.context.title' => 'Który klaster?',
    'module.k8s.context.cancel' => 'Zostaw obecny',
    'module.k8s.context.chosen' => 'Kontekst: {name}',
    'module.k8s.context.rejected' => 'Tej nazwy kontekstu nie da się podać kubectl.',
    'module.k8s.namespace.title' => 'Przestrzeń nazw',
    'module.k8s.namespace.prompt' => 'Nazwa',
    'module.k8s.namespace.chosen' => 'Przestrzeń nazw: {name}',
    'module.k8s.namespace.rejected' => 'To nie jest poprawna nazwa przestrzeni nazw.',

    // Czynności zmieniające.
    'module.k8s.apply.title' => 'Zastosuj plik',
    'module.k8s.apply.prompt' => 'Ścieżka',
    'module.k8s.delete.confirm' => 'Usunąć {name} z klastra? Tego nie da się cofnąć.',
    'module.k8s.delete.none' => 'Najpierw otwórz zasób, który ma zniknąć.',
    'module.k8s.delete.forbidden' => 'Serwer nie pozwala usuwać zasobów rodzaju {kind}.',
    'module.k8s.yaml.none' => 'Najpierw otwórz zasób — YAML dotyczy jednego z nich.',
    'module.k8s.action.working.apply' => 'Stosuję plik…',
    'module.k8s.action.working.delete' => 'Usuwam zasób…',
    'module.k8s.action.working.patchSecret' => 'Zapisuję sekret…',
    'module.k8s.action.done.apply' => 'Zastosowano: {name}',
    'module.k8s.action.done.delete' => 'Usunięto: {name}',
    'module.k8s.action.done.patchSecret' => 'Zapisano klucz: {name}',

    // Sekrety.
    'module.k8s.secret.none' => 'Ten zasób nie ma zamaskowanych wartości.',
    'module.k8s.secret.reveal.title' => 'Który klucz odsłonić?',
    'module.k8s.secret.edit.title' => 'Co zrobić z sekretem?',
    'module.k8s.secret.edit.text' => 'Zmień wartość (wpiszę tekst)',
    'module.k8s.secret.edit.base64' => 'Zmień wartość (wpiszę base64)',
    'module.k8s.secret.edit.add' => 'Dodaj klucz',
    'module.k8s.secret.edit.remove' => 'Skasuj klucz',
    'module.k8s.secret.edit.cancel' => 'Nic nie rób',
    'module.k8s.secret.key.title' => 'Który klucz zmienić?',
    'module.k8s.secret.remove.title' => 'Który klucz skasować?',
    'module.k8s.secret.remove.confirm' => 'Skasować klucz {key}? Tego nie da się cofnąć.',
    'module.k8s.secret.add.title' => 'Nowy klucz',
    'module.k8s.secret.add.prompt' => 'Nazwa klucza',
    'module.k8s.secret.value.text' => 'Wartość klucza {key} (tekst)',
    'module.k8s.secret.value.base64' => 'Wartość klucza {key} (base64)',
    'module.k8s.secret.value.prompt' => 'Wartość',
    'module.k8s.secret.value.notBase64' => 'To nie jest poprawny zapis base64.',

    // Klawisze.
    'module.k8s.key.expand' => 'Rozwiń lub zwiń gałąź',
    'module.k8s.key.expand.short' => 'rozwiń',
    'module.k8s.key.open' => 'Otwórz zasób',
    'module.k8s.key.open.short' => 'otwórz',
    'module.k8s.key.focus' => 'Przejdź między drzewem a treścią',
    'module.k8s.key.focus.short' => 'panel',
    'module.k8s.key.context' => 'Zmień kontekst w tym pliku',
    'module.k8s.key.context.short' => 'kontekst',
    'module.k8s.key.namespace' => 'Zmień przestrzeń nazw',
    'module.k8s.key.namespace.short' => 'przestrzeń',
    'module.k8s.key.yaml' => 'Pokaż surowy YAML',
    'module.k8s.key.yaml.short' => 'YAML',
    'module.k8s.key.logs' => 'Logi poda',
    'module.k8s.key.logs.short' => 'logi',
    'module.k8s.key.reveal' => 'Odsłoń wartość sekretu',
    'module.k8s.key.reveal.short' => 'odsłoń',
    'module.k8s.key.edit' => 'Zmień sekret',
    'module.k8s.key.edit.short' => 'zmień',
    'module.k8s.key.apply' => 'Zastosuj plik',
    'module.k8s.key.apply.short' => 'apply',
    'module.k8s.key.delete' => 'Usuń zasób',
    'module.k8s.key.delete.short' => 'usuń',
    'module.k8s.key.refresh' => 'Odśwież spis i listę',
    'module.k8s.key.refresh.short' => 'odśwież',
    'module.k8s.key.follow' => 'Wróć na koniec logu',
    'module.k8s.key.follow.short' => 'koniec',
    'module.k8s.key.back' => 'Zamknij logi',
    'module.k8s.key.back.short' => 'wróć',
    'module.k8s.key.retry' => 'Spytaj klaster jeszcze raz',
    'module.k8s.key.retry.short' => 'ponów',

    // Komendy.
    'module.k8s.command.get' => 'Pokaż zasoby wskazanego rodzaju',
    'module.k8s.command.context' => 'Wybierz klaster (kontekst kubectl)',
    'module.k8s.command.namespace' => 'Zmień przestrzeń nazw',
    'module.k8s.command.apply' => 'Zastosuj plik manifestu w klastrze',
    // Czynność `k8s.deploy-image` (krok 54).
    'module.k8s.command.deployImage' => 'Wdróż obraz kontenera w klastrze',
    'module.k8s.setting.buildWaitSeconds' => 'Limit czekania na budowę (s)',
    'module.k8s.setting.splitFraction' => 'Szerokość drzewa zasobów (%)',
    'module.k8s.deploy.pickImage' => 'Który obraz wdrożyć',
    'module.k8s.deploy.pickDeployment' => 'Gdzie wdrożyć {tag}',
    'module.k8s.deploy.buildNew' => '— zbuduj nowy obraz —',
    'module.k8s.deploy.waiting' => 'Przygotowywanie {tag}',
    'module.k8s.deploy.stage' => 'czekam na moduł Dockera…',
    'module.k8s.deploy.building' => 'Budowa zamówiona — wróć tu, gdy się skończy.',
    'module.k8s.deploy.noDocker' => 'Moduł Dockera jest wyłączony albo nieobecny — nie ma kogo zapytać o obrazy.',
    'module.k8s.deploy.imagesNotRead' => 'Moduł Dockera nie zna jeszcze obrazów — otwórz jego ekran (Ctrl+O, F3) i wróć tutaj.',
    'module.k8s.deploy.noDeployments' => 'Klaster nie ma wdrożeń do pokazania — otwórz ekran klastra (Ctrl+K) i wczytaj je.',
    'module.k8s.deploy.timedOut' => 'Upłynął limit czekania. Praca trwa dalej w module Dockera.',
    'module.k8s.deploy.abandoned' => 'Wdrażanie przerwane. Praca modułu Dockera idzie dalej.',
    'module.k8s.deploy.pushFailed' => 'Obraz nie trafił do rejestru — bez tego klaster go nie pobierze.',
    'module.k8s.deploy.pushRefused' => 'Rejestr nie przyjął obrazu: {reason}',
    'module.k8s.deploy.applying' => 'Podmiana obrazu w {deployment} na {tag} zamówiona.',
    'module.k8s.action.done.setImage' => 'Obraz podmieniony w: {name}',

    'module.k8s.command.noKind' => 'Klaster nie zna rodzaju „{kind}”.',
    'module.k8s.command.noCatalog' => 'Spis rodzajów jeszcze nie przyszedł — otwórz ekran klastra (Ctrl+K).',
    'module.k8s.argument.kind' => 'rodzaj',
    'module.k8s.argument.path' => 'ścieżka',

    // Kwerendy modułu (krok 54).
    'module.k8s.query.contexts' => 'konteksty z kubeconfig wraz z bieżącym',
    'module.k8s.query.cluster' => 'wersja klastra i klienta oraz etap sesji',
    'module.k8s.query.namespaces' => 'przestrzenie nazw znane sesji',
    'module.k8s.query.kinds' => 'rodzaje zasobów zgłoszone przez klaster',
    'module.k8s.query.resources' => 'wiersze zasobu wskazanego rodzaju wraz z etapem',
    'module.k8s.query.deployments' => 'wdrożenia wraz z obrazem każdego kontenera',
    'module.k8s.query.unknownKind' => 'Klaster nie zna takiego rodzaju zasobu.',
    'module.k8s.query.noDeployments' => 'Spis rodzajów jeszcze nie przyszedł albo klaster nie zna wdrożeń.',

    // Zdarzenia.
    'module.k8s.event.applied' => 'Klaster: zastosowano plik',
    'module.k8s.event.deleted' => 'Klaster: usunięto zasób',
    'module.k8s.event.secret.changed' => 'Klaster: zmieniono sekret',
    'module.k8s.event.connection.lost' => 'Klaster: czynność się nie powiodła',

    // Pomoc.
    'module.k8s.help.start' => 'Ctrl+K otwiera klaster. Po lewej drzewo: grupy API, w nich rodzaje zasobów, w nich zasoby.',
    'module.k8s.help.tree' => 'Enter rozwija gałąź — dopiero wtedy moduł pyta klaster o listę. Rodzaje pochodzą z klastra, więc zasoby własne (CRD) pojawiają się same.',
    'module.k8s.help.place' => 'c wybiera klaster, n zmienia przestrzeń nazw. Oba wybory są zapamiętywane; plik konfiguracyjny kubectl zostaje nietknięty.',
    'module.k8s.help.detail' => 'Enter na zasobie otwiera opis w zwijanych sekcjach, y przełącza na surowy YAML. Tab przechodzi między drzewem a treścią.',
    'module.k8s.help.logs' => 'l otwiera logi poda — płyną na żywo także wtedy, gdy patrzysz na co innego. Strzałka w górę zatrzymuje widok, End wraca na koniec.',
    'module.k8s.help.actions' => 'F5 stosuje plik (ścieżkę proponuje katalog przeglądarki), F8 usuwa zasób po potwierdzeniu. Ctrl+R odświeża spis i listę.',
    'module.k8s.help.secrets' => 'Wartości sekretów są zamaskowane. x odsłania jedną, e otwiera zmianę: wartość tekstem lub base64, dodanie klucza, skasowanie klucza.',
    'module.k8s.help.versions' => 'Nagłówek pokazuje obie wersje, gdy klient i serwer różnią się o więcej niż jedno wydanie. Moduł nie odmawia wtedy niczego — Kubernetes nazywa to niewspieranym, a nie niemożliwym.',
    'module.k8s.stage.missingFile' => 'Pliku {path} nie ma — popraw ścieżkę wpisu (c) albo podepnij dysk.',
    'module.k8s.stage.unknownContext' => 'W pliku {path} nie ma kontekstu {context} — wybierz inny klawiszem k.',
    'module.k8s.panel.clusters' => 'Klastry',
    'module.k8s.view.clusters' => 'Klaster — spis',
    'module.k8s.key.clusters' => 'Spis klastrów',
    'module.k8s.key.clusters.short' => 'klastry',
    'module.k8s.cluster.column.name' => 'Nazwa',
    'module.k8s.cluster.column.context' => 'Kontekst',
    'module.k8s.cluster.column.kubeconfig' => 'Plik',
    'module.k8s.cluster.column.origin' => 'Pochodzenie',
    'module.k8s.cluster.column.state' => 'Stan',
    'module.k8s.cluster.origin.own' => 'wpis własny',
    'module.k8s.cluster.origin.config' => 'plik domyślny',
    'module.k8s.cluster.origin.env' => 'KUBECONFIG',
    'module.k8s.cluster.state.current' => 'bieżący',
    'module.k8s.cluster.state.shadowed' => 'przysłonięty',
    'module.k8s.cluster.empty' => 'Spis klastrów jest pusty — dopisz wpis klawiszem F7.',
    'module.k8s.cluster.reading' => 'Czytam pliki kubeconfig…',
    'module.k8s.cluster.switched' => 'Klaster: {name}.',
    'module.k8s.cluster.removed' => 'Wpis {name} usunięty ze spisu.',
    'module.k8s.cluster.saved' => 'Wpis {name} zapisany.',
    'module.k8s.cluster.readEntry' => 'Wpis {name} pochodzi z cudzego pliku — moduł do kubeconfig nie pisze.',
    'module.k8s.cluster.book.unreadable' => 'Spisu klastrów nie da się przeczytać — plik stanu jest uszkodzony.',
    'module.k8s.cluster.problem.unknown' => 'Nie ma klastra o nazwie {name}.',
    'module.k8s.cluster.key.select' => 'Wybierz klaster',
    'module.k8s.cluster.key.select.short' => 'wybierz',
    'module.k8s.cluster.key.add' => 'Dopisz klaster',
    'module.k8s.cluster.key.add.short' => 'dopisz',
    'module.k8s.cluster.key.edit' => 'Zmień wpis',
    'module.k8s.cluster.key.edit.short' => 'zmień',
    'module.k8s.cluster.key.remove' => 'Usuń wpis',
    'module.k8s.cluster.key.remove.short' => 'usuń',
    'module.k8s.cluster.key.refresh' => 'Przeczytaj pliki od nowa',
    'module.k8s.cluster.confirm.remove' => 'Usunąć wpis {name} ze spisu klastrów?',
    'module.k8s.cluster.prompt.name' => 'Nazwa klastra',
    'module.k8s.cluster.prompt.name.field' => 'nazwa',
    'module.k8s.cluster.prompt.kubeconfig' => 'Plik kubeconfig',
    'module.k8s.cluster.prompt.kubeconfig.field' => 'ścieżka',
    'module.k8s.cluster.prompt.context' => 'Kontekst w tym pliku',
    'module.k8s.cluster.prompt.context.field' => 'kontekst',
    'module.k8s.cluster.prompt.namespace' => 'Przestrzeń nazw (puste — ta z pliku)',
    'module.k8s.cluster.prompt.namespace.field' => 'przestrzeń',
    'module.k8s.name.subject.cluster' => 'nazwa klastra',
    'module.k8s.name.subject.kubeconfig' => 'ścieżka kubeconfig',
    'module.k8s.query.clusters' => 'spis klastrów z książki i z plików kubeconfig',
];
