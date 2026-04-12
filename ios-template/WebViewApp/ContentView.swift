import SwiftUI
import WebKit

struct ContentView: View {
    @State private var isLoading = true
    @State private var hasError = false
    @State private var canGoBack = false

    var body: some View {
        ZStack {
            WebView(
                isLoading: $isLoading,
                hasError: $hasError,
                canGoBack: $canGoBack
            )

            if isLoading {
                ProgressView()
                    .scaleEffect(1.5)
                    .progressViewStyle(CircularProgressViewStyle(tint: .blue))
            }

            if hasError {
                VStack(spacing: 20) {
                    Image(systemName: "wifi.exclamationmark")
                        .font(.system(size: 60))
                        .foregroundColor(.gray)
                    Text("无法加载页面")
                        .font(.title2)
                        .foregroundColor(.primary)
                    Text("请检查网络连接后重试")
                        .font(.body)
                        .foregroundColor(.secondary)
                    Button(action: {
                        hasError = false
                        isLoading = true
                        NotificationCenter.default.post(name: .reload, object: nil)
                    }) {
                        Text("重新加载")
                            .padding(.horizontal, 32)
                            .padding(.vertical, 12)
                            .background(Color.blue)
                            .foregroundColor(.white)
                            .cornerRadius(10)
                    }
                }
                .frame(maxWidth: .infinity, maxHeight: .infinity)
                .background(Color(.systemBackground))
            }
        }
    }
}

extension Notification.Name {
    static let reload = Notification.Name("reloadWebView")
}

struct WebView: UIViewRepresentable {
    @Binding var isLoading: Bool
    @Binding var hasError: Bool
    @Binding var canGoBack: Bool

    func makeUIView(context: Context) -> WKWebView {
        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true

        let webView = WKWebView(frame: .zero, configuration: config)
        webView.navigationDelegate = context.coordinator
        webView.allowsBackForwardNavigationGestures = true
        webView.scrollView.bounces = true

        // 下拉刷新
        let refreshControl = UIRefreshControl()
        refreshControl.addTarget(
            context.coordinator,
            action: #selector(Coordinator.handleRefresh(_:)),
            for: .valueChanged
        )
        webView.scrollView.refreshControl = refreshControl

        // 加载 URL
        if let url = loadConfigURL() {
            webView.load(URLRequest(url: url))
        }

        // 监听重新加载通知
        NotificationCenter.default.addObserver(
            forName: .reload,
            object: nil,
            queue: .main
        ) { _ in
            if let url = loadConfigURL() {
                webView.load(URLRequest(url: url))
            }
        }

        return webView
    }

    func updateUIView(_ uiView: WKWebView, context: Context) {}

    func makeCoordinator() -> Coordinator {
        Coordinator(self)
    }

    private func loadConfigURL() -> URL? {
        guard let configPath = Bundle.main.path(forResource: "config", ofType: "json"),
              let data = try? Data(contentsOf: URL(fileURLWithPath: configPath)),
              let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any],
              let urlString = json["url"] as? String,
              let url = URL(string: urlString) else {
            return nil
        }
        return url
    }

    class Coordinator: NSObject, WKNavigationDelegate {
        var parent: WebView

        init(_ parent: WebView) {
            self.parent = parent
        }

        func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
            parent.isLoading = true
            parent.hasError = false
        }

        func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
            parent.isLoading = false
            parent.canGoBack = webView.canGoBack
        }

        func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
            parent.isLoading = false
            parent.hasError = true
        }

        func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
            parent.isLoading = false
            parent.hasError = true
        }

        @objc func handleRefresh(_ refreshControl: UIRefreshControl) {
            guard let webView = refreshControl.superview?.superview as? WKWebView else {
                refreshControl.endRefreshing()
                return
            }
            webView.reload()
            DispatchQueue.main.asyncAfter(deadline: .now() + 1) {
                refreshControl.endRefreshing()
            }
        }
    }
}
