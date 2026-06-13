package com.facturador.facturapro.data.remote

import org.junit.Assert.assertEquals
import org.junit.Test
import java.io.IOException

class ApiErrorMapperTest {
    @Test
    fun mapsNetworkFailureToActionableMessage() {
        val message = ApiErrorMapper.message(IOException("timeout"))

        assertEquals(
            "No se pudo conectar con FacturaPro. Verifica la API y la red.",
            message,
        )
    }
}
