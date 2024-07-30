# Configuration file for the Sphinx documentation builder.

# -- Project information

project = 'GeoSuite'
copyright = '2024, Cited, Inc.'
author = 'Cited Inc'

release = '2.1'
version = '0.1.0'

# -- General configuration

extensions = [
    'sphinx.ext.duration',
    'sphinx.ext.doctest',
    'sphinx.ext.autodoc',
    'sphinx.ext.autosummary',
    'sphinx.ext.intersphinx',
]

intersphinx_mapping = {
    'python': ('https://docs.python.org/3/', None),
    'sphinx': ('https://www.sphinx-doc.org/en/master/', None),
}
intersphinx_disabled_domains = ['std']

templates_path = ['_templates']

# -- Options for HTML output

html_theme = 'sphinx_rtd_theme'

# -- Options for EPUB output
epub_show_urls = 'footnote'


formats: all


html_static_path = ['_static']

# These paths are either relative to html_static_path
# or fully qualified paths (eg. https://...)
html_css_files = [
    'css/custom.css',
]

pygments_style = "sphinx"


html_logo = "acugis-geosuite-docs-logo.png"
html_theme_options = {
    'logo_only': True,
    'display_version': False,
}
