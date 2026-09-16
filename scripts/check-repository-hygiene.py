#!/usr/bin/env python3
"""Validate tracked Markdown links and reject generated repository artifacts."""

from __future__ import annotations

import re
import subprocess
import sys
import unicodedata
from html import unescape
from pathlib import Path
from urllib.parse import unquote, urlsplit


REPOSITORY_ROOT = Path(__file__).resolve().parent.parent
MARKDOWN_LINK = re.compile(
    r"!?\[[^\]]*\]\((?P<target><[^>]+>|[^\s)]+)(?:\s+(?:\"[^\"]*\"|'[^']*'))?\)"
)
REFERENCE_LINK = re.compile(
    r"^\s*\[[^\]]+\]:\s*(?P<target><[^>]+>|\S+)", re.MULTILINE
)
URI_SCHEME = re.compile(r"^[a-zA-Z][a-zA-Z0-9+.-]*:")
FENCE = re.compile(r"^\s*(`{3,}|~{3,})")
ATX_HEADING = re.compile(r"^\s{0,3}#{1,6}\s+(?P<title>.+?)\s*#*\s*$", re.MULTILINE)
INLINE_LINK_LABEL = re.compile(r"!?\[([^\]]*)\]\([^)]*\)")
HTML_TAG = re.compile(r"<[^>]+>")

FORBIDDEN_DIRECTORIES = {
    ".nitro",
    ".nuxt",
    ".output",
    ".mypy_cache",
    ".pytest_cache",
    ".ruff_cache",
    ".tmp",
    ".vite",
    "__pycache__",
    "coverage",
    "dist",
    "dist-ssr",
    "htmlcov",
    "node_modules",
    "playwright-report",
    "test-results",
    "vendor",
}
FORBIDDEN_NAMES = {
    ".DS_Store",
    "Thumbs.db",
    "coverage.xml",
    "desktop.ini",
    "junit.xml",
}
FORBIDDEN_SUFFIXES = {
    ".bak",
    ".backup",
    ".dump",
    ".log",
    ".orig",
    ".pyc",
    ".rej",
    ".sql",
    ".swo",
    ".swp",
    ".tsbuildinfo",
    ".tmp",
}
DATABASE_ARTIFACT = re.compile(
    r"\.(?:sql|dump|backup|bak)(?:\.(?:gz|bz2|xz|zst|zip))?$", re.IGNORECASE
)


def tracked_paths() -> list[Path]:
    result = subprocess.run(
        ["git", "ls-files", "-z"],
        cwd=REPOSITORY_ROOT,
        check=True,
        capture_output=True,
    )
    return [Path(raw.decode()) for raw in result.stdout.split(b"\0") if raw]


def strip_fenced_code(markdown: str) -> str:
    visible_lines: list[str] = []
    active_fence: str | None = None

    for line in markdown.splitlines():
        match = FENCE.match(line)
        if match:
            marker = match.group(1)[0]
            if active_fence is None:
                active_fence = marker
            elif active_fence == marker:
                active_fence = None
            visible_lines.append("")
            continue

        visible_lines.append(line if active_fence is None else "")

    return "\n".join(visible_lines)


def link_targets(markdown: str) -> list[str]:
    visible_markdown = strip_fenced_code(markdown)
    matches = list(MARKDOWN_LINK.finditer(visible_markdown))
    matches.extend(REFERENCE_LINK.finditer(visible_markdown))
    return [match.group("target").strip("<>") for match in matches]


def github_slug(title: str) -> str:
    plain_title = INLINE_LINK_LABEL.sub(r"\1", unescape(title.lower()))
    plain_title = HTML_TAG.sub("", plain_title)
    plain_title = plain_title.replace("`", "").replace("*", "").replace("~", "")
    characters = (
        character
        for character in plain_title
        if character.isspace()
        or character in "-_"
        or character.isalnum()
        or unicodedata.category(character).startswith("M")
    )
    return re.sub(r"\s+", "-", "".join(characters).strip())


def markdown_anchors(markdown: str) -> set[str]:
    anchors: set[str] = set()
    occurrences: dict[str, int] = {}

    for match in ATX_HEADING.finditer(strip_fenced_code(markdown)):
        base_anchor = github_slug(match.group("title"))
        occurrence = occurrences.get(base_anchor, 0)
        anchor = base_anchor if occurrence == 0 else f"{base_anchor}-{occurrence}"
        occurrences[base_anchor] = occurrence + 1
        anchors.add(anchor)

    return anchors


def validate_markdown_links(paths: list[Path]) -> list[str]:
    failures: list[str] = []
    anchor_cache: dict[Path, set[str]] = {}

    for relative_path in paths:
        if relative_path.suffix.lower() != ".md":
            continue

        document = REPOSITORY_ROOT / relative_path
        markdown = document.read_text(encoding="utf-8")
        for target in link_targets(markdown):
            if not target or target.startswith("//") or URI_SCHEME.match(target):
                continue

            parsed = urlsplit(target)
            link_path = unquote(parsed.path)
            destination = (document.parent / link_path).resolve() if link_path else document.resolve()
            try:
                destination.relative_to(REPOSITORY_ROOT)
            except ValueError:
                failures.append(f"{relative_path}: ссылка выходит за пределы репозитория: {target}")
                continue

            if not destination.exists():
                failures.append(f"{relative_path}: отсутствует цель ссылки: {target}")
                continue

            fragment = unquote(parsed.fragment)
            if fragment and destination.suffix.lower() == ".md":
                if destination not in anchor_cache:
                    destination_markdown = destination.read_text(encoding="utf-8")
                    anchor_cache[destination] = markdown_anchors(destination_markdown)
                if fragment not in anchor_cache[destination]:
                    failures.append(f"{relative_path}: отсутствует Markdown anchor: {target}")

    return failures


def validate_tracked_artifacts(paths: list[Path]) -> list[str]:
    failures: list[str] = []

    for path in paths:
        if any(part in FORBIDDEN_DIRECTORIES for part in path.parts):
            failures.append(f"запрещённый tracked-каталог: {path}")
            continue
        if path.name in FORBIDDEN_NAMES:
            failures.append(f"запрещённый tracked-файл: {path}")
            continue
        if (
            path.name.endswith("~")
            or path.suffix.lower() in FORBIDDEN_SUFFIXES
            or DATABASE_ARTIFACT.search(path.name)
        ):
            failures.append(f"запрещённый tracked-файл: {path}")

    return failures


def main() -> int:
    try:
        paths = tracked_paths()
    except (OSError, subprocess.CalledProcessError) as error:
        print(f"Не удалось получить список tracked-файлов: {error}", file=sys.stderr)
        return 2

    failures = validate_markdown_links(paths) + validate_tracked_artifacts(paths)
    if failures:
        print("Проверка документации и артефактов завершилась ошибкой:", file=sys.stderr)
        for failure in failures:
            print(f"- {failure}", file=sys.stderr)
        return 1

    markdown_count = sum(path.suffix.lower() == ".md" for path in paths)
    print(
        f"Проверены {markdown_count} Markdown-файлов и {len(paths)} tracked-файлов: ошибок нет."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
