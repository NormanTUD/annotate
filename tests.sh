#!/bin/bash

if command -v php 2>/dev/null >/dev/null; then
	php -l *.php

	exit_code=$?

	if [[ $exit_code -ne 0 ]]; then
		echo "php -l failed!"
		exit 1
	fi
else
	echo "PHP not installed. Cannot run php tests"
fi

bash docker.sh --local-port 9912 --instance-name annotate_test
exit_code=$?

if [[ $exit_code -ne 0 ]]; then
	echo "bash docker.sh --local-port 9912 --instance-name annotate_test failed with exit code $exit_code"
	exit $exit_code
fi

echo "====== Checking virtualenv ======"
if [[ ! -d ~/.annotate_test_env ]]; then
	python3 -m venv ~/.annotate_test_env
	source ~/.annotate_test_env/bin/activate

	if ! pip install linkchecker; then
		echo "pip install linkchecker failed. Are you online?"
		rm -rf ~/.annotate_test_env
		exit 1
	fi

	if ! pip install playwright; then
		echo "pip install playwright failed. Are you online?"
		rm -rf ~/.annotate_test_env
		exit 1
	fi
	playwright install chromium
	playwright install firefox
fi

source ~/.annotate_test_env/bin/activate

echo "====== linkchecker ======"

linkchecker localhost:9912

echo "====== pip install playwright ======"
if ! pip install playwright; then
	echo "pip install playwright failed. Are you online?"
	rm -rf ~/.annotate_test_env
	exit 1
fi

echo "====== playwright install chromium ======"
playwright install chromium

echo "====== python -m playwright install --with-deps chromium ======"

if ! which chromium 2> /dev/null >/dev/null; then
	sudo apt install chromium
fi

python -m playwright install --with-deps chromium

echo "====== Run tests ======"
python3 _run_tests.py $*
exit_code=$?
echo "====== Ran tests ======"
