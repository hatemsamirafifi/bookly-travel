import subprocess
import sys
import os
import glob

test_files = sorted(glob.glob("testsprite_tests/TC*.py"))
results = {}

print(f"Found {len(test_files)} tests to run.", flush=True)
os.makedirs("testsprite_tests/tmp", exist_ok=True)

py_bin = r"C:\Users\HaTeM\AppData\Local\Programs\Python\Python311\python.exe"
python_executable = py_bin if os.path.exists(py_bin) else sys.executable

for test_path in test_files:
    test_name = os.path.splitext(os.path.basename(test_path))[0]
    print(f"Running {test_name}...", flush=True)
    
    # Clear cache between tests to reset rate-limit counters without altering security policies
    subprocess.run(["docker", "exec", "bookly-backend", "php", "artisan", "cache:clear"], capture_output=True)
    
    try:
        proc = subprocess.run(
            [python_executable, test_path],
            capture_output=True,
            text=True,
            timeout=120
        )
        if proc.returncode == 0:
            status = "PASS"
            err = ""
        else:
            status = "FAIL"
            lines = (proc.stderr or proc.stdout).strip().splitlines()
            err = lines[-1] if lines else "Non-zero exit"
            # Print last few lines of error if failed
            print("\n".join(lines[-5:]), flush=True)
    except subprocess.TimeoutExpired:
        status = "TIMEOUT"
        err = "Test timed out after 120s"
    except Exception as e:
        status = "ERROR"
        err = str(e)
    
    results[test_name] = (status, err)
    print(f"  -> {test_name}: {status} {('(' + err + ')') if err else ''}", flush=True)

with open("testsprite_tests/tmp/run_all_results.txt", "w", encoding="utf-8") as f:
    for name, (stat, err) in results.items():
        f.write(f"{name}: {stat} (exit {0 if stat == 'PASS' else 1})\n")

print("\n--- Summary ---", flush=True)
passed = sum(1 for s, _ in results.values() if s == "PASS")
failed = sum(1 for s, _ in results.values() if s != "PASS")
print(f"Total: {len(results)}, Passed: {passed}, Failed: {failed}", flush=True)
