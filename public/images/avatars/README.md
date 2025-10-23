# Preset Avatars

This directory contains preset avatar images that users can select for their profile.

## Setup Instructions

1. Add avatar images named `preset-1.png`, `preset-2.png`, etc. (up to `preset-30.png`)
2. Recommended size: 256x256 pixels
3. Format: PNG or JPG
4. Each avatar should be themed around GTA 6 characters, vehicles, or iconic imagery

## Quick Setup

You can generate placeholder avatars using online tools like:
- https://ui-avatars.com/api/
- https://avatars.dicebear.com/
- Or use your own custom GTA-themed artwork

Example for generating placeholders:
```bash
# Using curl to download placeholder avatars
for i in {1..30}; do
  curl "https://ui-avatars.com/api/?name=Avatar+$i&size=256&background=random&color=fff" -o "preset-$i.png"
done
```

## Current Status

The system is configured to support 30 preset avatars. Users can select these from their profile settings page.
