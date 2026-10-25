"""Validate PR destinations without shell interpolation or access to hosting."""
import json
import os
from pathlib import Path


def validate(event_name, event, repository):
    if event_name != 'pull_request':
        return True, 'PR routing does not deploy code or restrict local branch tests.'
    request = event.get('pull_request', {})
    base = request.get('base', {}).get('ref')
    head = request.get('head', {}).get('ref', '')
    head_repository = request.get('head', {}).get('repo') or {}
    same_repository = head_repository.get('full_name') == repository
    if base == 'dev':
        return True, 'Integration PR targets dev.'
    if base == 'main' and same_repository and (head == 'dev' or (
        isinstance(head, str) and head.startswith('hotfix/') and len(head) > len('hotfix/')
    )):
        return True, 'Production code is promoted from dev or an explicit repository hotfix.'
    return False, 'Target dev for contributions; main accepts only this repository\'s dev or hotfix/... branches.'


def main():
    event_name = os.environ.get('GITHUB_EVENT_NAME', '')
    event_path = os.environ.get('GITHUB_EVENT_PATH')
    repository = os.environ.get('GITHUB_REPOSITORY', '')
    if not event_name or not event_path or not repository:
        print('Missing GitHub event context. This command is intended for CI.')
        return 1
    try:
        event = json.loads(Path(event_path).read_text(encoding='utf-8'))
        if not isinstance(event, dict):
            raise ValueError('Invalid event')
        allowed, message = validate(event_name, event, repository)
    except (OSError, ValueError, TypeError, AttributeError):
        print('Unable to validate the GitHub event; PR routing was not approved.')
        return 1
    print(message)
    return 0 if allowed else 1


if __name__ == '__main__':
    raise SystemExit(main())
