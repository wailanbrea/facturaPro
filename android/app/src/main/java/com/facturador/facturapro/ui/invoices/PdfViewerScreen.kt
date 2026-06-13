package com.facturador.facturapro.ui.invoices

import android.content.ContextWrapper
import android.net.Uri
import android.util.Log
import android.view.View
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.core.content.FileProvider
import androidx.fragment.app.FragmentActivity
import androidx.fragment.app.FragmentContainerView
import androidx.fragment.app.commit
import androidx.lifecycle.DefaultLifecycleObserver
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleOwner
import androidx.pdf.viewer.fragment.PdfViewerFragment
import java.io.File

private const val TAG = "FacturaProPDF"

@Composable
fun PdfViewerScreen(
    filePath: String,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val activity = remember(context) { context.findFragmentActivity() }

    val file = remember(filePath) { File(filePath) }
    val fileError = remember(filePath) {
        when {
            !file.exists() -> "No se encontró el archivo PDF local."
            file.length() <= 0L -> "El archivo PDF está vacío."
            else -> null
        }
    }

    val uri: Uri? = remember(filePath, fileError) {
        if (fileError != null) return@remember null
        runCatching {
            FileProvider.getUriForFile(
                context,
                "${context.packageName}.fileprovider",
                file,
            )
        }.onSuccess { generated ->
            Log.d(TAG, "Abriendo visor interno con: $filePath")
            Log.d(TAG, "Uri generado: $generated")
        }.onFailure {
            Log.e(TAG, "Error al generar Uri FileProvider: ${it.message}", it)
        }.getOrNull()
    }

    Column(
        modifier = modifier
            .fillMaxSize()
            .padding(horizontal = 16.dp),
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(top = 8.dp, bottom = 6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            IconButton(onClick = onBack) {
                Icon(
                    imageVector = Icons.AutoMirrored.Outlined.ArrowBack,
                    contentDescription = "Volver",
                    tint = MaterialTheme.colorScheme.onSurface,
                )
            }
            Text(
                text = "Factura PDF",
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
        }

        HorizontalDivider()

        when {
            fileError != null -> {
                Log.e(TAG, "Error al abrir PDF: $fileError ($filePath)")
                PdfErrorBox(message = fileError)
            }

            uri == null -> {
                PdfErrorBox(message = "No se pudo generar el Uri del PDF.")
            }

            activity == null -> {
                Log.e(TAG, "Error al abrir PDF: contexto no es FragmentActivity")
                PdfErrorBox(
                    message = "No se puede mostrar el PDF: la actividad no soporta fragments.",
                )
            }

            else -> {
                PdfFragmentHost(
                    activity = activity,
                    uri = uri,
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(top = 4.dp, bottom = 8.dp),
                )
            }
        }
    }
}

@Composable
private fun PdfFragmentHost(
    activity: FragmentActivity,
    uri: Uri,
    modifier: Modifier = Modifier,
) {
    val containerId = rememberSaveable { View.generateViewId() }
    val fragmentTag = remember(containerId) { "pdf-viewer-fragment-$containerId" }

    AndroidView(
        modifier = modifier,
        factory = { ctx ->
            FragmentContainerView(ctx).apply {
                id = containerId
            }
        },
        update = {
            val fragmentManager = activity.supportFragmentManager
            val existing = fragmentManager.findFragmentByTag(fragmentTag) as? PdfViewerFragment

            if (existing == null) {
                val newFragment = PdfViewerFragment()
                fragmentManager.commit(allowStateLoss = true) {
                    add(containerId, newFragment, fragmentTag)
                }
                Log.d(TAG, "Fragment PDF agregado, esperando STARTED para cargar Uri")
                newFragment.setUriWhenStarted(uri)
            } else if (existing.documentUri != uri) {
                existing.setUriWhenStarted(uri)
                Log.d(TAG, "Fragment PDF reutilizado con nuevo Uri: $uri")
            }
        },
    )

    DisposableEffect(containerId, uri) {
        onDispose {
            val fragmentManager = activity.supportFragmentManager
            if (!fragmentManager.isStateSaved && !fragmentManager.isDestroyed) {
                fragmentManager.findFragmentByTag(fragmentTag)?.let { fragment ->
                    fragmentManager.commit(allowStateLoss = true) {
                        remove(fragment)
                    }
                }
            }
        }
    }
}

@Composable
private fun PdfErrorBox(message: String) {
    Box(
        modifier = Modifier
            .fillMaxSize()
            .padding(PaddingValues(16.dp)),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(8.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Text(
                text = "No se pudo mostrar el PDF",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                color = MaterialTheme.colorScheme.error,
            )
            Text(
                text = message,
                style = MaterialTheme.typography.bodyMedium,
                color = MaterialTheme.colorScheme.onSurfaceVariant,
            )
        }
    }
}

private fun android.content.Context.findFragmentActivity(): FragmentActivity? {
    var current: android.content.Context? = this
    while (current is ContextWrapper) {
        if (current is FragmentActivity) return current
        current = current.baseContext
    }
    return null
}

private fun PdfViewerFragment.setUriWhenStarted(uri: Uri) {
    if (lifecycle.currentState.isAtLeast(Lifecycle.State.STARTED)) {
        documentUri = uri
        Log.d(TAG, "Fragment PDF cargando Uri (ya STARTED): $uri")
        return
    }
    lifecycle.addObserver(object : DefaultLifecycleObserver {
        override fun onStart(owner: LifecycleOwner) {
            documentUri = uri
            Log.d(TAG, "Fragment PDF cargando Uri tras STARTED: $uri")
            lifecycle.removeObserver(this)
        }
    })
}
