"""Make composer work without Packagist: add a git (vcs) repo to the global composer config for
every package composer says it cannot find, mapping vendor/name to its GitHub repository."""
import json, os, re, subprocess, sys

HOME = os.environ['COMPOSER_HOME']
CFG = os.path.join(HOME, 'config.json')
ALIASES = {  # composer vendor -> GitHub owner, or full package -> owner/repo
    'orchestra': 'orchestral', 'phpunit': 'sebastianbergmann', 'sebastian': 'sebastianbergmann',
    'psr': 'php-fig', 'league': 'thephpleague', 'guzzlehttp': 'guzzle', 'monolog': 'Seldaek',
    'phpdocumentor': 'phpDocumentor', 'carbonphp': 'CarbonPHP', 'fidry': 'theofidry',
    'nesbot/carbon': 'briannesbitt/Carbon', 'graham-campbell/result-type': 'GrahamCampbell/Result-Type',
    'phpoption/phpoption': 'schmittjoh/php-option', 'sebastian/object-enumerator': 'sebastianbergmann/object-enumerator',
    'symfony/polyfill-php83': 'symfony/polyfill-php83', 'psr/http-message': 'php-fig/http-message',
    'psr/simple-cache': 'php-fig/simple-cache', 'psr/event-dispatcher': 'php-fig/event-dispatcher',
    'psr/http-factory': 'php-fig/http-factory', 'psr/http-client': 'php-fig/http-client',
    'psr/clock': 'php-fig/clock', 'psr/log': 'php-fig/log', 'psr/container': 'php-fig/container',
    'tijsverkoyen/css-to-inline-styles': 'tijsverkoyen/CssToInlineStyles',
    'ta-tikoma/phpunit-architecture-test': 'ta-tikoma/phpunit-architecture-test',
    'myclabs/deep-copy': 'myclabs/DeepCopy', 'phar-io/manifest': 'phar-io/manifest', 'phar-io/version': 'phar-io/version',
    'theseer/tokenizer': 'theseer/tokenizer', 'doctrine/lexer': 'doctrine/lexer',
    'laravel/framework': 'laravel/framework',
    'dflydev/dot-access-data': 'dflydev/dflydev-dot-access-data', 'egulias/email-validator': 'egulias/EmailValidator',
    'phpdocumentor/reflection-common': 'phpDocumentor/ReflectionCommon', 'phpdocumentor/reflection-docblock': 'phpDocumentor/ReflectionDocBlock',
    'phpdocumentor/type-resolver': 'phpDocumentor/TypeResolver', 'psy/psysh': 'bobthecow/psysh', 'phpstan/phpdoc-parser': 'phpstan/phpdoc-parser',
}

def repo_for(pkg):
    if pkg in ALIASES:
        return ALIASES[pkg]
    vendor, name = pkg.split('/')
    return f"{ALIASES.get(vendor, vendor)}/{name}"

def exists(slug):
    r = subprocess.run(['git', 'ls-remote', '--heads', f'https://github.com/{slug}.git'], capture_output=True, timeout=60)
    return r.returncode == 0

def load():
    return json.load(open(CFG)) if os.path.exists(CFG) else {}

def add(pkgs):
    cfg = load()
    repos = cfg.setdefault('repositories', [{'packagist.org': False}])
    known = {r.get('url') for r in repos if isinstance(r, dict)}
    added = []
    for pkg in pkgs:
        slug = repo_for(pkg)
        url = f'https://github.com/{slug}.git'
        if url in known:
            continue
        if not exists(slug):
            print(f'!! no GitHub repo for {pkg} (tried {slug})'); continue
        repos.append({'type': 'vcs', 'url': url, 'no-api': True})
        known.add(url); added.append(pkg)
    json.dump(cfg, open(CFG, 'w'), indent=2)
    return added

def run(project, extra):
    for i in range(80):
        r = subprocess.run(['composer', 'update', '--no-interaction', '--no-progress', *extra], cwd=project,
                           capture_output=True, text=True)
        out = r.stdout + r.stderr
        if r.returncode == 0:
            print('RESOLVED in', i, 'rounds'); print(out[-1500:]); return 0
        missing = set(re.findall(r'requires? ([\w.-]+/[\w.-]+)[^\n]*?(?:it could not be found|-> could not be found)', out))
        missing |= set(re.findall(r'Root composer.json requires ([\w.-]+/[\w.-]+), it could not be found', out))
        missing = {m for m in missing if not m.startswith(('ext-', 'lib-'))}
        added = add(sorted(missing))
        print(f'round {i}: missing {sorted(missing)} added {added}', flush=True)
        if not added:
            print(out[-4000:]); return 1
    return 1

if __name__ == '__main__':
    sys.exit(run(sys.argv[1], sys.argv[2:]))
