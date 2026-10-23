from pathlib import Path

PRODUCT_NAME = "Reseñas WordPress"
OLD_PRODUCT_NAME = "Kairoseth Reviews"
STORE_SLUG = "gestion-resenas"
OLD_STORE_SLUG = "kairoseth-reviews"
STORE_CLASS = "Gestion_Resenas"
OLD_STORE_CLASS = "Kairoseth_Reviews"

ROOT = Path(".")
SELF = Path(__file__).resolve()
SKIP_DIRS = {".git", ".github", "build", "dist", "node_modules"}
TEXT_SUFFIXES = {
    ".php", ".py", ".sh", ".yml", ".yaml", ".json", ".txt", ".md",
    ".po", ".pot", ".js", ".css"
}

changed = []
for path in ROOT.rglob("*"):
    if not path.is_file():
        continue
    if path.resolve() == SELF:
        continue
    if any(part in SKIP_DIRS for part in path.parts):
        continue
    if path.suffix.lower() not in TEXT_SUFFIXES and path.name != ".wp-env.json":
        continue

    try:
        text = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        continue

    updated = text.replace(OLD_PRODUCT_NAME, PRODUCT_NAME)
    updated = updated.replace(OLD_STORE_SLUG, STORE_SLUG)
    updated = updated.replace(OLD_STORE_CLASS, STORE_CLASS)

    if updated != text:
        path.write_text(updated, encoding="utf-8")
        changed.append(str(path))

# Rename bundled language catalog files so the filename matches the Store slug/text-domain.
lang_dir = ROOT / "languages"
if lang_dir.exists():
    for old_path in sorted(lang_dir.glob(f"{OLD_STORE_SLUG}*")):
        new_path = old_path.with_name(old_path.name.replace(OLD_STORE_SLUG, STORE_SLUG, 1))
        if new_path.exists():
            new_path.unlink()
        old_path.rename(new_path)
        changed.append(f"{old_path} -> {new_path}")

print(f"Commercial identity enforced: {PRODUCT_NAME} / {STORE_SLUG} / {STORE_CLASS}")
for item in changed:
    print(f" - {item}")
