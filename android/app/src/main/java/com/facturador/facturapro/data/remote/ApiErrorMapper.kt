package com.facturador.facturapro.data.remote

import retrofit2.HttpException
import java.io.IOException

object ApiErrorMapper {
    fun message(error: Throwable): String = when (error) {
        is HttpException -> when (error.code()) {
            401 -> "Sesion expirada o credenciales invalidas."
            403 -> "Tu usuario no tiene permisos para esta operacion."
            422 -> "Revisa los datos enviados."
            in 500..599 -> "El servidor no pudo procesar la solicitud."
            else -> "Solicitud rechazada por el servidor (${error.code()})."
        }
        is IOException -> "No se pudo conectar con FacturaPro. Verifica la API y la red."
        else -> "Ocurrio un error inesperado."
    }
}
