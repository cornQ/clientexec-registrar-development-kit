# Official Sources

Use current upstream material rather than treating this kit as a replacement for Clientexec documentation.

## Primary sources

- [Domain Registrar Plugin Development Guide](https://docs.clientexec.com/en/article/domain-registrar-plugin-development-guide-i9wpwd/)
- [Official sample registrar](https://github.com/clientexec/sample-registrar)
- [Clientexec system requirements](https://docs.clientexec.com/en/article/system-requirements-yl750s/)

The registrar development guide used during this audit reported an update date of 30 October 2024. Recheck the live page before relying on that date or on version-sensitive behavior.

## How to use the sources

1. Use the official guide for naming, configuration, advertised feature flags, actions, and documented callbacks.
2. Use the official sample for concrete parameter and return shapes.
3. Use target-installation reflection for abstract methods and inherited helpers.
4. Use target-installation schema/runtime behavior for extended attributes and internal dispatch.
5. Use production registrar modules only to discover patterns that need verification.

## Attribution and redistribution

Link to upstream documentation and repositories. Do not copy an entire upstream article, sample module, proprietary Clientexec core file, registrar SDK, or production module into a distributable plugin unless its license explicitly permits that use.

Independently author explanations and templates. Preserve required license notices for any third-party dependency intentionally included in a generated registrar module.
