#!/bin/bash

my_python() {
	if command -v python3 &>/dev/null; then
		echo "Trying python3..."
		python3 "$@"
	elif command -v python3.11 &>/dev/null; then
		echo "Trying python3.11..."
		python3.11 "$@"
	elif command -v python &>/dev/null; then
		echo "Trying python..."
		python "$@"
	else
		echo "Error: No Python interpreter found!" >&2
		return 1
	fi
}

my_pip() {
	if command -v pip3 &>/dev/null; then
		echo "Trying pip3..."
		pip3 "$@"
	elif command -v pip3.11 &>/dev/null; then
		echo "Trying pip3.11..."
		pip3.11 "$@"
	elif command -v pip &>/dev/null; then
		echo "Trying pip..."
		pip "$@"
	else
		echo "Error: No pip found!" >&2
		return 1
	fi
}

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

bash -n *.sh
exit_code=$?

if [[ $exit_code -ne 0 ]]; then
	echo "Some bash scripts had syntax errors"
	exit 1
fi

bash -x docker.sh --local-port 9912 --instance-name annotate_test
exit_code=$?

if [[ $exit_code -ne 0 ]]; then
	echo "bash docker.sh --local-port 9912 --instance-name annotate_test failed with exit code $exit_code"
	exit $exit_code
fi

#bash tests/upload_model
#exit_code=$?
#if [[ $exit_code -ne 0 ]]; then
#	echo "bash tests/upload_model failed with exit_code $exit_code"
#	exit 1
#fi

echo "====== Checking virtualenv ======"
if [[ ! -d ~/.annotate_test_env ]]; then
	my_python -m venv ~/.annotate_test_env
	source ~/.annotate_test_env/bin/activate

	if ! my_pip install linkchecker; then
		echo "my_pip install linkchecker failed. Are you online?"
		rm -rf ~/.annotate_test_env
		exit 1
	fi

	if ! my_pip install playwright; then
		echo "my_pip install playwright failed. Are you online?"
		rm -rf ~/.annotate_test_env
		exit 1
	fi
	playwright install chromium
	playwright install firefox
fi

source ~/.annotate_test_env/bin/activate

echo "====== linkchecker ======"

linkchecker http://localhost:9912
exit_code=$?

if [[ $exit_code -ne 0 ]]; then
	echo "linkchecker failed"
	exit 5
fi

echo "====== my_pip install playwright ======"
if ! my_pip install playwright; then
	echo "my_pip install playwright failed. Are you online?"
	rm -rf ~/.annotate_test_env
	exit 1
fi

echo "====== playwright install chromium ======"
playwright install chromium

echo "====== my_python -m playwright install --with-deps chromium ======"

if ! which chromium 2> /dev/null >/dev/null; then
	sudo apt install chromium
fi

my_python -m playwright install --with-deps chromium

echo "====== Run tests ======"
my_python _run_tests.py $*
exit_code=$?
echo "====== Ran tests ======"
