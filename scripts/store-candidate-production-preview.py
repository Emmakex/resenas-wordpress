from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PHP = ROOT / "resenas-wordpress.php"
README = ROOT / "readme.txt"

php = PHP.read_text()

# Promote the validated production preview to the final WordPress.org release.
for candidate in ("1.2.0-rc4", "1.2.0-rc3", "1.2.0-rc2"):
    php = php.replace(f"Version: {candidate}", "Version: 1.2.0")
    php = php.replace(f"const VERSION = '{candidate}';", "const VERSION = '1.2.0';")

PHP.write_text(php)

readme = README.read_text()
for candidate in ("1.2.0-rc4", "1.2.0-rc3", "1.2.0-rc2"):
    readme = readme.replace(f"Stable tag: {candidate}", "Stable tag: 1.2.0")

# Remove release-candidate language from the public WordPress.org readme.
preview_lines = (
    "* RC4 production preview aligns the real frontend and admin UI with the final premium visual direction.\n",
    "* RC4 is intended for final live visual acceptance before the WordPress.org submission package is promoted to 1.2.0.\n",
    "* Final RC4 packaging is gated by strict WordPress Plugin Check validation before release.\n",
)
for line in preview_lines:
    readme = readme.replace(line, "")

readme = readme.replace("New records in the Store Candidate", "New records in Gestión de Reseñas 1.2.0")
readme = readme.replace("the WordPress.org Store identity", "the WordPress.org release")
readme = readme.replace("When the Store package is activated", "When the WordPress.org package is activated")
readme = readme.replace("the new Store package", "the new WordPress.org package")
readme = readme.replace("* Store package migration preserves", "* WordPress.org package migration preserves")

final_line = "* Final WordPress.org release of the premium frontend and WordPress-native admin experience.\n"
if final_line not in readme:
    readme = readme.replace("= 1.2.0 =\n", "= 1.2.0 =\n" + final_line)

README.write_text(readme)
