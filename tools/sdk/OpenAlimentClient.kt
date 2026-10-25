package ro.openaliment.sdk

import android.net.Uri
import android.util.Base64
import org.json.JSONObject
import java.io.ByteArrayOutputStream
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest
import java.security.SecureRandom

/** Run network calls on an IO executor. The caller supplies its secure token store. */
class OpenAlimentClient(private val tokenProvider: () -> String? = { null }) {
    fun request(method: String, path: String, body: JSONObject? = null, idempotencyKey: String? = null): JSONObject {
        require(path.startsWith("/") && !path.startsWith("//") && !path.contains('\\'))
        val connection = URL("https://openaliment.ro$path").openConnection() as HttpURLConnection
        connection.instanceFollowRedirects = false
        connection.requestMethod = method
        connection.connectTimeout = 15000
        connection.readTimeout = 15000
        connection.setRequestProperty("Accept", "application/json")
        tokenProvider()?.let { connection.setRequestProperty("Authorization", "Bearer $it") }
        idempotencyKey?.let { connection.setRequestProperty("Idempotency-Key", it) }
        try {
            if (body != null) {
                connection.doOutput = true
                connection.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            }
            val status = connection.responseCode
            val stream = if (status in 200..299) connection.inputStream else connection.errorStream
            val bytes = ByteArrayOutputStream()
            stream?.use { input ->
                val block = ByteArray(8192)
                while (true) {
                    val count = input.read(block)
                    if (count < 0) break
                    require(bytes.size() + count <= 8_000_000) { "API response too large" }
                    bytes.write(block, 0, count)
                }
            }
            val text = bytes.toString("UTF-8")
            val result = if (text.isBlank()) JSONObject() else JSONObject(text)
            if (status !in 200..299) throw OpenAlimentError(
                status, result.optString("error_code", result.optString("error", "http_error")),
                result.optString("request_id", connection.getHeaderField("X-Request-ID") ?: ""),
                connection.getHeaderField("Retry-After")
            )
            return result
        } finally { connection.disconnect() }
    }

    fun food(code: String): JSONObject = request("GET", "/api/v2/public/food?" +
        Uri.Builder().appendQueryParameter("code", code).build().encodedQuery)

    data class PendingAuthorization(val url: String, val verifier: String, val state: String)
    companion object {
        private fun random(size: Int): String {
            val bytes = ByteArray(size); SecureRandom().nextBytes(bytes)
            return Base64.encodeToString(bytes, Base64.URL_SAFE or Base64.NO_WRAP or Base64.NO_PADDING)
        }
        fun authorize(clientId: String, redirectUri: String, scopes: String = "home:read home:write"): PendingAuthorization {
            val verifier = random(48); val state = random(32)
            val challenge = Base64.encodeToString(MessageDigest.getInstance("SHA-256").digest(verifier.toByteArray(Charsets.US_ASCII)),
                Base64.URL_SAFE or Base64.NO_WRAP or Base64.NO_PADDING)
            val url = Uri.parse("https://openaliment.ro/oauth/authorize").buildUpon()
                .appendQueryParameter("client_id", clientId).appendQueryParameter("redirect_uri", redirectUri)
                .appendQueryParameter("response_type", "code").appendQueryParameter("scope", scopes)
                .appendQueryParameter("state", state).appendQueryParameter("code_challenge", challenge)
                .appendQueryParameter("code_challenge_method", "S256").build().toString()
            return PendingAuthorization(url, verifier, state)
        }
        fun exchangeBody(clientId: String, redirectUri: String, callback: Uri, pending: PendingAuthorization): JSONObject {
            require(callback.getQueryParameter("state") == pending.state) { "Invalid OAuth state" }
            require(callback.getQueryParameter("iss") == "https://openaliment.ro") { "Invalid OAuth issuer" }
            require(callback.getQueryParameter("error") == null) { "Authorization declined" }
            val expected = Uri.parse(redirectUri)
            require(callback.scheme == expected.scheme && callback.authority == expected.authority && callback.path == expected.path) { "Invalid callback" }
            val code = requireNotNull(callback.getQueryParameter("code"))
            return JSONObject().put("grant_type", "authorization_code").put("client_id", clientId)
                .put("redirect_uri", redirectUri).put("code", code).put("code_verifier", pending.verifier)
        }
    }
}
class OpenAlimentError(val status: Int, val code: String, val requestId: String, val retryAfter: String?) :
    Exception("OpenAliment API: $status $code ($requestId)")
