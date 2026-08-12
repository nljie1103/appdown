# AppDown Template Builder 2.0

Template Builder is the default fast path for URL → WebView app generation.

It does **not** compile user source code on every build. AppDown ships two precompiled
mother packages:

- `builder/templates/android-webview-template.apk`
- `builder/templates/ios-webview-template.ipa`

At runtime the multi-architecture Docker image patches app metadata/configuration and
re-signs the package.

## Android fast path

`template.apk → APKEditor patch/rebuild → JKS signing → signature verification`

The runtime image does not require Android SDK, Gradle or aapt/aapt2.

## iOS fast path

`template.ipa → config/Info.plist patch → zsign + P12 + mobileprovision → signed IPA`

The runtime image does not require macOS or Xcode. Full Xcode compilation remains
available as the advanced builder for future shell/source changes.

## Runtime architecture

The image is built natively on the Docker host and is intended for both
`linux/amd64` and `linux/arm64`. It only receives per-task read-only input/secrets
and a writable output mount.

Third-party projects are pinned/downloaded from their upstream repositories during
image build. See `Dockerfile` for versions and licenses.
