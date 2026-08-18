package com.facturador.facturapro.ui.invoices

import android.content.ContentValues
import android.content.Context
import android.content.Intent
import android.graphics.Bitmap
import android.graphics.Color
import android.graphics.pdf.PdfRenderer
import android.net.Uri
import android.os.Environment
import android.os.ParcelFileDescriptor
import android.provider.MediaStore
import android.util.Log
import android.widget.Toast
import androidx.compose.foundation.Image
import androidx.compose.foundation.gestures.rememberTransformableState
import androidx.compose.foundation.gestures.transformable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.itemsIndexed
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.outlined.ArrowBack
import androidx.compose.material.icons.outlined.Download
import androidx.compose.material.icons.outlined.Share
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.core.content.FileProvider
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.IOException

private const val TAG = "FacturaProPDF"

@Composable
fun PdfViewerScreen(
    filePath: String,
    onBack: () -> Unit,
    title: String = "Factura PDF",
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val file = remember(filePath) { File(filePath) }
    val fileError = remember(filePath) {
        when {
            !file.exists() -> "No se encontró el archivo PDF local."
            file.length() <= 0L -> "El archivo PDF está vacío."
            else -> null
        }
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
                text = title,
                style = MaterialTheme.typography.titleLarge,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            IconButton(onClick = { sharePdf(context, file) }, enabled = fileError == null) {
                Icon(Icons.Outlined.Share, contentDescription = "Compartir PDF")
            }
            IconButton(onClick = { savePdfToDownloads(context, file) }, enabled = fileError == null) {
                Icon(Icons.Outlined.Download, contentDescription = "Guardar en Descargas")
            }
        }

        HorizontalDivider()

        if (fileError != null) {
            Log.e(TAG, "Error al abrir PDF: $fileError ($filePath)")
            PdfErrorBox(message = fileError)
        } else {
            PdfRendererContent(
                file = file,
                modifier = Modifier
                    .fillMaxSize()
                    .padding(top = 4.dp, bottom = 8.dp),
            )
        }
    }
}

private data class PdfPageBitmap(
    val index: Int,
    val bitmap: Bitmap,
    val width: Int,
    val height: Int,
)

@Composable
private fun PdfRendererContent(
    file: File,
    modifier: Modifier = Modifier,
) {
    var isLoading by remember { mutableStateOf(true) }
    var renderError by remember { mutableStateOf<String?>(null) }
    val pages = remember { mutableStateListOf<PdfPageBitmap>() }

    var scale by remember { mutableFloatStateOf(1f) }
    var offset by remember { mutableStateOf(Offset.Zero) }

    val transformState = rememberTransformableState { zoomChange, offsetChange, _ ->
        scale = (scale * zoomChange).coerceIn(1f, 4f)
        if (scale > 1f) {
            offset += offsetChange
        } else {
            offset = Offset.Zero
        }
    }

    LaunchedEffect(file.absolutePath) {
        isLoading = true
        renderError = null
        pages.clear()
        withContext(Dispatchers.IO) {
            runCatching {
                val pfd = ParcelFileDescriptor.open(file, ParcelFileDescriptor.MODE_READ_ONLY)
                val renderer = PdfRenderer(pfd)
                val count = renderer.pageCount
                val rendered = mutableListOf<PdfPageBitmap>()
                for (i in 0 until count) {
                    val page = renderer.openPage(i)
                    val targetWidth = (page.width * 2).coerceAtMost(2400)
                    val targetHeight = (page.height * 2).coerceAtMost(3200)
                    val bmp = Bitmap.createBitmap(targetWidth, targetHeight, Bitmap.Config.ARGB_8888)
                    val canvas = android.graphics.Canvas(bmp)
                    canvas.drawColor(Color.WHITE)
                    page.render(bmp, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
                    page.close()
                    rendered.add(PdfPageBitmap(i, bmp, targetWidth, targetHeight))
                }
                renderer.close()
                pfd.close()
                rendered
            }.onSuccess { list ->
                pages.addAll(list)
                isLoading = false
            }.onFailure { err ->
                Log.e(TAG, "Error renderizando PDF con PdfRenderer: ${err.message}", err)
                renderError = "Error al procesar el documento PDF: ${err.localizedMessage ?: "formato no reconocido"}"
                isLoading = false
            }
        }
    }

    when {
        isLoading -> {
            Box(modifier = modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(
                    horizontalAlignment = Alignment.CenterHorizontally,
                    verticalArrangement = Arrangement.spacedBy(8.dp),
                ) {
                    CircularProgressIndicator()
                    Text("Cargando documento...", style = MaterialTheme.typography.bodySmall)
                }
            }
        }

        renderError != null -> {
            PdfErrorBox(message = renderError ?: "Error desconocido")
        }

        else -> {
            Box(
                modifier = modifier
                    .fillMaxSize()
                    .graphicsLayer(
                        scaleX = scale,
                        scaleY = scale,
                        translationX = offset.x,
                        translationY = offset.y,
                    )
                    .transformable(state = transformState),
            ) {
                LazyColumn(
                    modifier = Modifier.fillMaxSize(),
                    contentPadding = PaddingValues(vertical = 12.dp),
                    verticalArrangement = Arrangement.spacedBy(16.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    itemsIndexed(pages) { idx, pageItem ->
                        Column(
                            horizontalAlignment = Alignment.CenterHorizontally,
                            modifier = Modifier.fillMaxWidth(),
                        ) {
                            Surface(
                                shadowElevation = 4.dp,
                                shape = RoundedCornerShape(8.dp),
                                modifier = Modifier
                                    .fillMaxWidth()
                                    .aspectRatio(pageItem.width.toFloat() / pageItem.height.toFloat())
                                    .clip(RoundedCornerShape(8.dp)),
                            ) {
                                Image(
                                    bitmap = pageItem.bitmap.asImageBitmap(),
                                    contentDescription = "Página ${idx + 1}",
                                    modifier = Modifier.fillMaxSize(),
                                )
                            }
                            Spacer(Modifier.height(4.dp))
                            Text(
                                text = "Página ${idx + 1} de ${pages.size}",
                                style = MaterialTheme.typography.labelSmall,
                                color = MaterialTheme.colorScheme.onSurfaceVariant,
                            )
                        }
                    }
                }
            }
        }
    }
}

private fun sharePdf(context: Context, file: File) {
    val uri = runCatching {
        FileProvider.getUriForFile(context, "${context.packageName}.fileprovider", file)
    }.getOrElse {
        Toast.makeText(context, "No se pudo preparar el PDF para compartir.", Toast.LENGTH_LONG).show()
        return
    }

    context.startActivity(Intent.createChooser(Intent(Intent.ACTION_SEND).apply {
        type = "application/pdf"
        putExtra(Intent.EXTRA_STREAM, uri)
        addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
    }, "Compartir PDF"))
}

private fun savePdfToDownloads(context: Context, file: File) {
    runCatching {
        val values = ContentValues().apply {
            put(MediaStore.Downloads.DISPLAY_NAME, file.name)
            put(MediaStore.Downloads.MIME_TYPE, "application/pdf")
            put(MediaStore.Downloads.RELATIVE_PATH, Environment.DIRECTORY_DOWNLOADS + "/FacturaPro")
            put(MediaStore.Downloads.IS_PENDING, 1)
        }
        val uri = context.contentResolver.insert(MediaStore.Downloads.EXTERNAL_CONTENT_URI, values)
            ?: throw IOException("No se pudo crear el archivo en Descargas.")
        context.contentResolver.openOutputStream(uri)?.use { output -> file.inputStream().use { input -> input.copyTo(output) } }
            ?: throw IOException("No se pudo guardar el PDF.")
        values.clear()
        values.put(MediaStore.Downloads.IS_PENDING, 0)
        context.contentResolver.update(uri, values, null, null)
        Toast.makeText(context, "PDF guardado en Descargas/FacturaPro", Toast.LENGTH_LONG).show()
    }.onFailure {
        Toast.makeText(context, "No se pudo guardar el PDF.", Toast.LENGTH_LONG).show()
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
