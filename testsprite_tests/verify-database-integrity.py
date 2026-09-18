#!/usr/bin/env python3
"""Database integrity gate runner.

Executes verify-database-integrity.sql (DBI-001..DBI-020) against the
bookly-postgres container via psql, parses each `check_id|violations` row,
and fails closed (exit 1) on any violation > 0 or psql error. Exits 0 only
when all 20 invariants report 0 violations.
"""

import os
import re
import subprocess
import sys

SQL_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "verify-database-integrity.sql")

EXPECTED_CHECKS = [f"DBI-{i:03d}" for i in range(1, 21)]
ROW_PATTERN = re.compile(r"^(DBI-\d{3})\|(\d+)$")


def main() -> int:
    try:
        with open(SQL_FILE, "r", encoding="utf-8") as handle:
            sql_content = handle.read()
    except OSError as exc:
        print(f"[ERROR] Cannot read {SQL_FILE}: {exc}", file=sys.stderr)
        return 1

    result = subprocess.run(
        ["docker", "exec", "-i", "bookly-postgres", "psql", "-U", "bookly",
         "-d", "bookly", "-A", "-F", "|", "-t"],
        input=sql_content,
        capture_output=True,
        text=True,
    )

    if result.returncode != 0 or result.stderr.strip():
        print(f"[ERROR] psql failed (exit {result.returncode}): {result.stderr.strip()}", file=sys.stderr)
        return 1

    parsed: dict[str, int] = {}
    for line in result.stdout.splitlines():
        line = line.strip()
        if not line:
            continue
        match = ROW_PATTERN.match(line)
        if not match:
            print(f"[ERROR] Unparseable psql output line: {line!r}", file=sys.stderr)
            return 1
        check_id, violations = match.groups()
        parsed[check_id] = int(violations)

    failures = 0
    for check_id in EXPECTED_CHECKS:
        if check_id not in parsed:
            print(f"[FAIL] {check_id}: missing from psql output", file=sys.stderr)
            failures += 1
            continue
        violations = parsed[check_id]
        if violations == 0:
            print(f"[PASS] {check_id}: 0 violation(s)")
        else:
            print(f"[FAIL] {check_id}: {violations} violation(s)", file=sys.stderr)
            failures += 1

    unexpected = sorted(set(parsed) - set(EXPECTED_CHECKS))
    for check_id in unexpected:
        print(f"[WARN] Unexpected check id in output: {check_id}", file=sys.stderr)

    if failures:
        print(f"Database integrity gate: {failures} check(s) failed.", file=sys.stderr)
        return 1

    print("Database integrity gate: all 20 checks passed.")
    return 0


if __name__ == "__main__":
    sys.exit(main())