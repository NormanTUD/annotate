old=$1
new=$2

for i in $(ls annotations/*/*/*.json); do
	if grep "$old" $i 2>&1 >/dev/null; then
		sed -i "s/\"$old/\"$new/g" $i
	fi
done
