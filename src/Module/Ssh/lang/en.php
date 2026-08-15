<?php

declare(strict_types=1);

/*
 * Strings of the "Remote session" module — English (fallback catalogue).
 *
 * **Every key must start with `module.ssh.`** — the catalogue accepts only
 * those and skips the rest.
 */

return [
    'module.ssh.name' => 'Remote session',
    'module.ssh.description' => 'An SSH connection to a host from the book — with a remembered key fingerprint and status in the bar.',

    'module.ssh.unavailable.client' => 'no OpenSSH client (ssh, ssh-keyscan)',

    'module.ssh.command.connect' => 'connect to a host from the book',
    'module.ssh.command.disconnect' => 'close the remote session',
    'module.ssh.command.hosts' => 'show the host book',
    'module.ssh.argument.host' => 'name of an entry in the host book',

    'module.ssh.setting.timeout' => 'Connection timeout (s)',
    'module.ssh.setting.auth' => 'Authentication method',
    'module.ssh.setting.remember' => 'Remember fingerprints of new hosts',
    'module.ssh.auth.agent' => 'agent',
    'module.ssh.auth.key' => 'key file',
    'module.ssh.auth.password' => 'password',

    'module.ssh.screen.hosts' => 'Hosts',
    'module.ssh.focus.hosts' => 'Host book',
    'module.ssh.column.name' => 'Name',
    'module.ssh.column.target' => 'User and host',
    'module.ssh.column.auth' => 'Method',
    'module.ssh.column.state' => 'State',
    'module.ssh.empty' => 'The book is empty — add a host with F7.',
    'module.ssh.header.session' => '{stage}: {host}',

    'module.ssh.stage.idle' => 'disconnected',
    'module.ssh.stage.probing' => 'checking',
    'module.ssh.stage.approval' => 'awaiting consent',
    'module.ssh.stage.connecting' => 'connecting',
    'module.ssh.stage.checking' => 'refreshing',
    'module.ssh.stage.connected' => 'connected',
    'module.ssh.stage.failed' => 'failed',

    'module.ssh.key.move' => 'choose host',
    'module.ssh.key.connect' => 'connect or disconnect',
    'module.ssh.key.connect.short' => 'connect',
    'module.ssh.key.auth' => 'change authentication method',
    'module.ssh.key.auth.short' => 'method',
    'module.ssh.key.refresh' => 'check session state',
    'module.ssh.key.refresh.short' => 'state',
    'module.ssh.key.add' => 'add host',
    'module.ssh.key.add.short' => 'add',
    'module.ssh.key.remove' => 'remove host from the book',
    'module.ssh.key.remove.short' => 'remove',

    'module.ssh.prompt.host' => 'New host',
    'module.ssh.prompt.host.field' => 'user@host:port ',
    'module.ssh.prompt.key' => 'Key for {host}',
    'module.ssh.prompt.key.field' => 'key path ',
    'module.ssh.prompt.password' => 'Password for {host}',
    'module.ssh.prompt.password.field' => 'password ',
    'module.ssh.progress.connecting' => 'Connecting to {host}',
    'module.ssh.confirm.remove' => 'Remove the entry "{host}" from the book?',
    'module.ssh.confirm.fingerprint' => 'Host {host} is unknown. Key fingerprint: {fingerprint}. Trust it and add it to the known hosts?',

    'module.ssh.message.connected' => 'Connected to {host}.',
    'module.ssh.message.disconnected' => 'Disconnected from {host}.',
    'module.ssh.message.cancelled' => 'Connection to {host} cancelled.',
    'module.ssh.message.added' => 'Host "{host}" added.',
    'module.ssh.message.removed' => 'Host "{host}" removed.',
    'module.ssh.message.auth' => 'Host "{host}" now authenticates with: {auth}.',
    'module.ssh.message.unknown' => 'There is no host "{host}" in the book.',
    'module.ssh.message.nothing' => 'No session is open.',

    'module.ssh.problem.failed' => 'Could not connect to {host}.',
    'module.ssh.problem.key-changed' => 'WARNING: the host key of {host} differs from the remembered one. Connection refused.',
    'module.ssh.problem.key-rejected' => 'The host key of {host} was not accepted.',
    'module.ssh.problem.denied' => 'Access to {host} denied — authentication failed.',
    'module.ssh.problem.unresolved' => 'Could not resolve the host name {host}.',
    'module.ssh.problem.refused' => 'Host {host} refused the connection.',
    'module.ssh.problem.timeout' => 'Host {host} did not answer in time.',
    'module.ssh.problem.unreachable' => 'Host {host} is unreachable.',
    'module.ssh.problem.closed' => 'Host {host} closed the connection.',
    'module.ssh.problem.key-permissions' => 'The key file permissions are too open.',
    'module.ssh.problem.key-missing' => 'The key file was not found.',
    'module.ssh.problem.unknown-host' => 'Host {host} is unknown and remembering fingerprints is turned off.',
    'module.ssh.problem.dropped' => 'The session with {host} was dropped.',
    'module.ssh.problem.interrupted' => 'Connecting to {host} was interrupted by other background work.',

    'module.ssh.profile.name.empty' => 'The host name is empty.',
    'module.ssh.profile.name.invalid' => 'The name "{name}" is too long or contains control characters.',
    'module.ssh.profile.host.invalid' => 'This is not a valid host name or address: "{host}".',
    'module.ssh.profile.user.invalid' => 'This is not a valid user name: "{user}".',
    'module.ssh.profile.port.invalid' => 'The port number must be between 1 and 65535.',
    'module.ssh.profile.key.invalid' => 'The key path must be absolute: "{path}".',

    'module.ssh.book.unreadable' => 'The host book could not be read — saving will overwrite it.',

    'module.ssh.event.connected' => 'remote session opened',
    'module.ssh.event.disconnected' => 'remote session closed',
    'module.ssh.event.failed' => 'remote connection failed',

    'module.ssh.help.start' => 'Ctrl+S opens the host book; the ssh.hosts command does the same.',
    'module.ssh.help.hosts' => 'Add hosts with F7 as user@host:port; the book lives in ~/.light-manager/ssh.json and survives a restart.',
    'module.ssh.help.auth' => 'The default authentication method is the SSH agent. Passwords are never stored — you are asked on every connection.',
    'module.ssh.help.fingerprint' => 'An unknown host stops the connection with a question about the key fingerprint. After you agree, the OpenSSH client itself appends the entry to ~/.ssh/known_hosts; a key that differs from the remembered one is refused without asking.',
    'module.ssh.help.refresh' => 'F5 refreshes the session state. The application does not check it every frame, because each check is a separate process — a session dropped by the network may briefly show as alive.',
];
