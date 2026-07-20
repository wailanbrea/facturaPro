package com.facturador.facturapro.data.remote

import org.json.JSONObject
import retrofit2.HttpException
import java.io.IOException

object ApiErrorMapper {
    fun message(error: Throwable): String = when (error) {
        is HttpException -> httpMessage(error)
        is IOException -> "No se pudo conectar con FacturaPro. Verifica la API y la red."
        else -> "Ocurrio un error inesperado."
    }

    private fun httpMessage(error: HttpException): String {
        val serverMessage = error.response()?.errorBody()?.string()
            ?.let(::parseServerMessage)
            ?.takeIf { it.isNotBlank() }

        return when (error.code()) {
            401 -> "Sesion expirada o credenciales invalidas."
            403 -> "Tu usuario no tiene permisos para esta operacion."
            422 -> serverMessage ?: "Revisa los datos enviados."
            429 -> "Demasiados intentos. Espera un momento e intenta nuevamente."
            // En produccion Laravel devuelve siempre el literal "Server Error", que no
            // le dice nada al usuario: en ese caso preferimos nuestro propio mensaje.
            in 500..599 -> serverMessage?.takeUnless { it.equals("Server Error", ignoreCase = true) }
                ?: "El servidor no esta disponible (error ${error.code()}). Intentalo mas tarde o avisa al administrador."
            else -> serverMessage ?: "Solicitud rechazada por el servidor (${error.code()})."
        }
    }

    private fun parseServerMessage(body: String): String? = runCatching {
        val json = JSONObject(body)
        val errors = json.optJSONObject("errors")
        val firstField = errors?.keys()?.asSequence()?.firstOrNull()
        val firstError = firstField
            ?.let(errors::optJSONArray)
            ?.optString(0)
            ?.takeIf { it.isNotBlank() }

        firstError ?: json.optString("message").takeIf { it.isNotBlank() }
    }.getOrNull()
}
