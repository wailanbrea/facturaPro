package com.facturador.facturapro.ui.workspace

import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test

class WorkspaceMenuTest {
    @Test
    fun facturador_sees_only_configured_sections() {
        val permissions = setOf(
            "crear_factura",
            "editar_factura",
            "emitir_factura",
            "ver_factura",
            "descargar_pdf",
            "registrar_pagos",
            "gestionar_clientes",
            "ver_reportes",
            "ver_informes",
            "crear_informes",
            "editar_informes",
            "descargar_informes",
            "configurar_informes",
            "ver_calendario",
            "gestionar_citas",
        )

        assertEquals(
            listOf(
                WorkspaceSection.Home,
                WorkspaceSection.Invoices,
                WorkspaceSection.Clients,
                WorkspaceSection.TechnicalReports,
                WorkspaceSection.More,
            ),
            visibleBottomItems(permissions).map { it.section },
        )
        assertEquals(
            listOf(WorkspaceSection.Reports, WorkspaceSection.Calendar),
            visibleMoreSections(permissions),
        )
        assertFalse(WorkspaceSection.Settings.isAllowed(permissions))
    }

    @Test
    fun administrator_sees_settings_and_all_more_sections() {
        val permissions = setOf(
            "ver_factura",
            "ver_reportes",
            "ver_calendario",
            "configurar_sistema",
        )

        assertTrue(WorkspaceSection.Settings.isAllowed(permissions))
        assertEquals(
            listOf(
                WorkspaceSection.Reports,
                WorkspaceSection.Calendar,
                WorkspaceSection.Settings,
            ),
            visibleMoreSections(permissions),
        )
    }
}
