#!/usr/bin/env python3
with open("features.py", "rb") as f:
    data = f.read()
# Replace raw bytes that are non-UTF-8
fixed = data.replace(b"\x97", b"-")
fixed = fixed.replace(b"\xe2\x80\x94", b"-")
fixed = fixed.replace(b"\xe2\x80\x93", b"-")
with open("features.py", "wb") as f:
    f.write(fixed)
print("features.py encoding fixed")

