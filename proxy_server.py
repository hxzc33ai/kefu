#!/usr/bin/env python3
import os
import sys
import urllib.request
import urllib.error
from http.server import ThreadingHTTPServer, SimpleHTTPRequestHandler

ROOT = os.path.dirname(os.path.abspath(__file__))
BACKEND = os.environ.get("BACKEND_URL", "http://127.0.0.1:8081")


class ProxyHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=ROOT, **kwargs)

    def end_headers(self):
        self.send_header("Cache-Control", "no-store")
        self.send_header("Pragma", "no-cache")
        super().end_headers()

    def do_OPTIONS(self):
        if self.path.startswith("/api/") or self.path.startswith("/ws/"):
            self.send_response(200)
            origin = self.headers.get("Origin", "*")
            self.send_header("Access-Control-Allow-Origin", origin)
            self.send_header("Access-Control-Allow-Methods", "GET,POST,PUT,DELETE,PATCH,OPTIONS")
            self.send_header("Access-Control-Allow-Headers", self.headers.get("Access-Control-Request-Headers", "content-type,authorization"))
            self.send_header("Access-Control-Allow-Credentials", "true")
            self.end_headers()
            return
        super().do_OPTIONS()

    def do_GET(self):
        if self.path.startswith("/api/") or self.path.startswith("/ws/"):
            self._proxy_request()
            return
        self._serve_static_or_index()

    def do_POST(self):
        if self.path.startswith("/api/") or self.path.startswith("/ws/"):
            self._proxy_request()
            return
        self.send_error(404, "Not Found")

    def do_PUT(self):
        if self.path.startswith("/api/") or self.path.startswith("/ws/"):
            self._proxy_request()
            return
        self.send_error(404, "Not Found")

    def do_DELETE(self):
        if self.path.startswith("/api/") or self.path.startswith("/ws/"):
            self._proxy_request()
            return
        self.send_error(404, "Not Found")

    def do_PATCH(self):
        if self.path.startswith("/api/") or self.path.startswith("/ws/"):
            self._proxy_request()
            return
        self.send_error(404, "Not Found")

    def _serve_static_or_index(self):
        path = self.translate_path(self.path)
        if self.path.endswith("/") or self.path in ("", "/"):
            self.path = "/index.html"
            self._serve_file(self.translate_path(self.path))
            return
        if not os.path.exists(path):
            self._serve_file(os.path.join(ROOT, "index.html"))
            return
        self._serve_file(path)

    def _serve_file(self, file_path):
        if not os.path.exists(file_path):
            self.send_error(404, "Not Found")
            return
        if os.path.isdir(file_path):
            file_path = os.path.join(file_path, "index.html")
        try:
            with open(file_path, "rb") as fh:
                payload = fh.read()
        except OSError:
            self.send_error(500, "Internal Server Error")
            return
        if file_path.endswith(".html"):
            content_type = "text/html; charset=utf-8"
        else:
            content_type = self.guess_type(file_path)
        self.send_response(200)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(payload)))
        self.end_headers()
        self.wfile.write(payload)

    def _proxy_request(self):
        target_url = BACKEND + self.path
        headers = {}
        for key, value in self.headers.items():
            if key.lower() in {"host", "connection", "content-length", "transfer-encoding", "keep-alive"}:
                continue
            headers[key] = value
        data = None
        if self.command in {"POST", "PUT", "PATCH", "DELETE"}:
            length = int(self.headers.get("Content-Length", "0"))
            if length > 0:
                data = self.rfile.read(length)

        req = urllib.request.Request(target_url, data=data, method=self.command, headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=20) as response:
                payload = response.read()
                self.send_response(response.status)
                for key, value in response.headers.items():
                    if key.lower() in {"content-length", "transfer-encoding", "connection", "keep-alive", "proxy-authenticate", "proxy-authorization", "te", "trailer", "upgrade"}:
                        continue
                    if key.lower() in {"access-control-allow-origin", "access-control-allow-credentials", "access-control-allow-methods", "access-control-allow-headers"}:
                        continue
                    self.send_header(key, value)
                origin = self.headers.get("Origin", "http://127.0.0.1:8080")
                self.send_header("Access-Control-Allow-Origin", origin)
                self.send_header("Access-Control-Allow-Credentials", "true")
                self.send_header("Access-Control-Allow-Methods", "GET,POST,PUT,DELETE,PATCH,OPTIONS")
                self.send_header("Access-Control-Allow-Headers", "content-type,authorization")
                self.end_headers()
                if payload:
                    self.wfile.write(payload)
        except urllib.error.HTTPError as error:
            payload = error.read()
            self.send_response(error.code)
            self.send_header("Content-Type", error.headers.get_content_type() or "application/json")
            origin = self.headers.get("Origin", "http://127.0.0.1:8080")
            self.send_header("Access-Control-Allow-Origin", origin)
            self.send_header("Access-Control-Allow-Credentials", "true")
            self.end_headers()
            if payload:
                self.wfile.write(payload)
        except Exception as error:
            self.send_error(502, f"Bad Gateway: {error}")


if __name__ == "__main__":
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8080
    server = ThreadingHTTPServer(("0.0.0.0", port), ProxyHandler)
    print(f"Serving {ROOT} on http://0.0.0.0:{port} and proxying /api/* and /ws/* to {BACKEND}")
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
