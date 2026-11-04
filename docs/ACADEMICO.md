# SIGA — Acuerdos académicos y matrículas

Versión documental: 2. Fecha de consolidación: 2026-11-04.

## Estado y alcance

**Estado funcional: APROBADO. Estado de entrega de los requisitos nuevos: PLANIFICADO; implementación, pruebas e integración pendientes de acreditar.** Esta publicación documenta acuerdos; no certifica funcionalidades existentes ni crea tablas, rutas, cuentas o permisos. Los controles centrales existentes se reutilizan y su evidencia permanece en sus documentos de dominio.

Fuente: revisión del usuario de la matriz integral, archivo `respuesta-matriz.docx` y precisiones posteriores aprobadas en la conversación SIGA. No se atribuyen aprobadores institucionales ni fechas de firma no acreditados. La devolución previa con logo no constituyó aprobación de los pendientes.

Documento funcional de referencia del Backend. El Frontend consume estas reglas y mantiene su aplicación en su propio `ACADEMICO.md`. Los contratos y nombres físicos se conciliarán con código y esquema vigentes antes de implementar. La única base institucional es `siga`; no crear bases de prueba. Respaldos previos cuando se modifique código o datos.

## Acuerdos vigentes G F D A R

| Código | Aspecto | Definición vigente |
|---|---|---|
| G01 | Organización | Generaciones, escalones, sedes y grupos. Los grupos se dividen posteriormente en especialización de campo o gabinete. |
| G02 | Cambios de sede | Las generaciones y los grupos pueden cambiar de sede. Falta precisar el tratamiento temporal y la relación entre ambos cambios. |
| G03 | Identidad | Persona es la identidad central. Cadete, personal formador y Usuario mantienen relaciones diferenciadas sin duplicar la identidad. |
| G04 | Autenticación | El acceso interactivo a SIGA utiliza el Usuario asignado a la Persona. La participación académica no crea cuentas ni concede acceso automáticamente. |
| G05 | Autorización | Laravel verifica permisos efectivos y alcance mediante PermissionResolver y DENY/ALLOW. Los perfiles y funciones académicas no autorizan por sí solos. |
| G06 | Historial | Conservar el contexto académico y las asignaciones históricas. Los cambios posteriores no deben reasignar actividades o evaluaciones anteriores a otra persona. |
| G07 | Baja académica | Es distinta de la baja de Persona. No se convierte automáticamente en baja de Persona ni implica por sí sola una decisión sobre acceso de Usuario. |

| Código | Aspecto | Definición vigente |
|---|---|---|
| F01 | Funciones simultáneas | Una misma Persona puede ser docente e instructor, incluso durante el mismo periodo. |
| F02 | Vinculación | El personal formador puede ser interno o externo a la institución. Esta condición no concede permisos. |
| F03 | Asignación | Identificar Persona, función, materia o práctica, grupo y periodo de participación. |
| F04 | Sustitución | Conservar responsable anterior y nuevo con sus fechas. Las actividades y evaluaciones mantienen el responsable que correspondía en ese momento. |
| F05 | Evaluación | Los cadetes evalúan a docentes e instructores, tanto internos como externos. |
| F06 | Instrumento común | Se utiliza el mismo cuestionario para docentes e instructores, con control de versiones y referencia a la versión aplicada. |
| F07 | Alcance de captura | El docente o instructor asignado registra las calificaciones correspondientes a sus asignaciones, sujeto a permiso efectivo y validación de pertenencia del cadete. |

| Código | Aspecto | Definición vigente |
|---|---|---|
| D01 | Momento | Evaluar al terminar cada materia o práctica, distinguiendo la asignación y función evaluadas. |
| D02 | Apertura | Automática conforme a programación o manual mediante operador autorizado. Una segunda apertura no duplica periodo ni evaluaciones. |
| D03 | Plazo | Apertura y cierre configurables por periodo. Laravel verifica el plazo al recibir la evaluación. |
| D04 | Elegibilidad | Validar que el cadete haya participado en la formación correspondiente y que coincidan asignación, contexto y versión del cuestionario. |
| D05 | Consulta | Directivos y administrador consultan progreso y resultados agregados durante el periodo y después del cierre. Durante el periodo, los resultados son provisionales. |
| D06 | Confidencialidad | Mínimo de cinco evaluaciones válidas por conjunto realmente evaluado: Persona formadora, actividad, periodo, versión de cuestionario y funciones identificadas; también se exige en filtros y exportaciones. Nunca mostrar resultados si los cruces permiten inferir respuestas individuales. Una evaluación conjunta cuenta una sola vez. |
| D07 | Exclusiones | Excluir evaluaciones invalidadas. Si el conjunto baja de cinco válidas, ocultar sus resultados incluso después del cierre. El acceso agregado no concede acceso a identidades ni respuestas individuales. |
| D08 | Rectificación | Directivos y administrador pueden autorizarla con permiso específico. Invalidar la original sin eliminar respuestas; vincular la nueva y conservar justificación e historial. Impedir dos evaluaciones válidas de la misma participación. |
| D09 | Reapertura | Directivos y administrador pueden reabrir para pendientes y rectificaciones autorizadas. Conservar periodo, cuestionario, contexto y nuevos plazos. Quien ya tiene evaluación válida no vuelve a responder. |

| Código | Aspecto | Definición vigente |
|---|---|---|
| A01 | Escala | De 0.0 a 10.0, con un decimal. Aprobar con 7.0 o más. Una calificación pendiente no equivale a cero. |
| A02 | Precisión | Sin redondeo. Si existe un cálculo aplicable, truncar el resultado final a un decimal y conservar precisión intermedia. Ejemplo: 6.95 queda en 6.9. |
| A03 | Captura | Docente o instructor asignado, individualmente o mediante carga masiva Excel. Validar permisos, asignación y pertenencia al grupo. |
| A04 | Carga masiva | Mostrar vista previa de válidos y errores antes de confirmar. Identificar cambios de calificaciones existentes como correcciones; no sobrescribir silenciosamente. |
| A05 | Visibilidad | El cadete ve sus calificaciones inmediatamente después de guardarse, incluidas correcciones y cargas masivas confirmadas. |
| A06 | Corrección | Ventana móvil de 12 horas desde el último guardado válido. Solo una modificación efectivamente registrada reinicia el plazo; consultas, intentos fallidos y cargas rechazadas no lo reinician. Desde las 12 horas exactas requiere autorización del administrador para una corrección concreta. |
| A07 | Tiempo e historial | Consultas e intentos fallidos no reinician el plazo. En carga masiva, solo cambian las efectivamente registradas o modificadas. Conservar valor anterior, nuevo, actor y fecha. |
| A08 | Consulta | Directivos y administrador: todas las calificaciones e historiales. Formador: exclusivamente sus asignaciones. Cadete: únicamente sus propios datos. Consultar no concede modificar. |

| Código | Aspecto | Definición vigente |
|---|---|---|
| A09 | Ordinario | Una calificación final ordinaria. Si es menor a 7.0, procede una oportunidad extraordinaria. |
| A10 | Extraordinario | Una sola oportunidad extraordinaria por materia o práctica. Registrar el valor obtenido sin limitarlo a 7.0; conservar también el ordinario. |
| A11 | Recursamiento | Si no aprueba el extraordinario, procede un solo recursamiento. Tiene una calificación final, sin otro extraordinario. |
| A12 | Baja por desempeño | Si no acredita el único recursamiento, registrar automáticamente la baja por desempeño como resultado pendiente de ratificación, visible sin esperar ratificación. La baja definitiva requiere ratificación. |
| A13 | Paso a especialización | Todas las materias y prácticas comunes deben estar aprobadas. El avance a especialización exige ratificación de los resultados que lo sustentan; mostrar automáticamente el cumplimiento académico no confirma el avance. |
| A14 | Acreditación final | Acreditar todas las materias y prácticas comunes y de especialización; no exigir asistencia mínima ni otros requisitos. El resultado es automático, pero su formalización y constancia definitiva requieren ratificación. |
| A15 | Perfil de egreso | Formación acreditada en campo o en gabinete. Asociar Persona, generación, versión del plan, especialización, fecha y calificaciones de sustento. |
| A16 | Alcance del perfil | El perfil de egreso es un resultado académico y no concede permisos ni habilita la cuenta de Usuario. |

| Código | Aspecto | Definición vigente |
|---|---|---|
| R01 | Calificación | SIGA refleja automáticamente el valor guardado y su resultado académico. El administrador o personal administrativo autorizado ratifica la calificación. |
| R02 | Acreditación | Cuando se cumplen todos los requisitos, SIGA registra automáticamente la acreditación correspondiente; queda sujeta a ratificación. |
| R03 | Pérdida de requisitos | Una corrección que elimina requisitos refleja automáticamente el retiro de acreditación pendiente de ratificación. Ratificar formaliza el nuevo estado, no modifica calificaciones ni repara la causa; conservar antecedentes. |
| R04 | Baja | La reprobación del único recursamiento genera baja por desempeño pendiente de ratificación; no se espera la ratificación para mostrarla. |
| R05 | Separación de acciones | Ratificar no modifica la calificación. Corregir requiere su propio procedimiento, alcance y permiso. |
| R06 | Cambio posterior | Una modificación posterior deja pendiente de nueva ratificación el resultado afectado. Conservar actor, fecha e historial de ratificaciones. |
| R07 | Control de permisos | Administrador o personal administrativo autorizado requiere permiso específico. No extender automáticamente esta atribución a coordinadores o a todo directivo. |

## Selecciones P01 a P24

Las precisiones posteriores de las secciones siguientes prevalecen sobre formulaciones generales anteriores.

| Código | Selección y definición |
|---|---|
| P01 | B — Configurar reglas propias por programa de mandos. |
| P02 | B — Permitir migrar inscripciones a otra versión mediante equivalencias autorizadas e historial. |
| P03 | B — Generación y grupos cambian de sede por operaciones independientes. |
| P04 | A — Asignar campo o gabinete individualmente y después conformar grupos. |
| P05 | B — Exigir revisión administrativa de equivalencias antes de trasladar pendientes. |
| P06 | B — Sujetar una nueva incorporación a resolución administrativa individual. |
| P07 | B — Separar estado documental y situación académica en catálogos independientes. |
| P08 | A — Registrar coordinador y disciplinario como funciones con asignación temporal a grupos. |
| P09 | B — Admitir solo .xlsx mediante plantilla única. |
| P10 | B — Confirmar únicamente las filas válidas, informando las rechazadas. |
| P11 | A — Permitir correcciones masivas expresas con vista previa e historial por calificación. |
| P12 | A — Administrador autoriza una corrección concreta para una calificación. |
| P13 | B — Exigir autorización desde que se cumplen exactamente las 12 horas. |
| P14 | B — El nuevo asignado solo captura lo que corresponde a su vigencia; tramitar antecedentes por separado. |
| P15 | A — Ratificar individualmente o por lote con detalle de cada calificación. |
| P16 | A — Aplicar avance, acreditación o baja desde el resultado automático, identificándolo pendiente. |
| P17 | B — Registrar no ratificado y abrir expediente de resolución; conservar el resultado y su evidencia. |
| P18 | A — Autorizar ratificación por generación o sede asignada. |
| P19 | A — Usar el cuestionario institucional existente y versionarlo al incorporarlo. |
| P20 | B — Una evaluación por persona y actividad, identificando las funciones que desempeñó. |
| P21 | B — Permitir una lista operativa de pendientes a un permiso separado, sin enlazar identidades con respuestas. |
| P22 | B — El operador autorizado inicia el trámite a petición del cadete y registra la solicitud. |
| P23 | B — Incluir comentarios con revisión para evitar datos identificables antes de su consulta autorizada. |
| P24 | A — Además del mínimo de cinco, restringir cruces que permitan deducir respuestas por diferencias. |

## Evaluación conjunta P20 y confidencialidad D06

Cuando la misma Persona sea docente e instructor en una actividad, cada cadete emite una sola evaluación por Persona y actividad, identificando ambas funciones. No genera dos calificaciones ni duplica respuestas. Solo se admiten preguntas diferenciadas si el cuestionario versionado las contempla expresamente.

La unidad realmente evaluada conserva Persona formadora, actividad, periodo, versión del cuestionario y funciones identificadas. El mínimo de cinco se calcula sobre evaluaciones válidas de esa unidad; las funciones no multiplican el conteo. Se excluyen invalidadas y se vuelven a ocultar resultados si el conjunto baja de cinco. Se restringen filtros, cruces y diferencias que permitan inferir respuestas, incluso con cinco o más evaluaciones.

Rechazar y auditar intentos de replicar o dividir una evaluación conjunta para simular evaluaciones independientes; no incrementar el conteo válido. La restricción no impide una nueva emisión por rectificación autorizada, que sustituye la evaluación invalidada conservando el vínculo y el historial.

Progreso y resultados agregados: solo directivos y administrador con permisos efectivos. La lista operativa de pendientes requiere permiso separado y no vincula identidades con respuestas. Comentarios abiertos requieren revisión previa para evitar datos identificables antes de mostrarse a usuarios autorizados.

## Resultados y ratificación C02

C02=A queda precisado por la decisión más reciente: reflejar automáticamente calificación y consecuencia, con su estado de ratificación en consultas, reportes y exportaciones. El resultado automático no equivale a resultado ratificado.

La ratificación no cambia valores ni subsana requisitos; registra actor, fecha y estado. Una modificación posterior deja pendiente de nueva ratificación el resultado afectado. Si no se ratifica, registrar «no ratificado», abrir expediente de resolución y conservar resultado y evidencia.

Mientras esté pendiente, no formalizar baja definitiva ni emitir constancia definitiva. El avance efectivo a especialización y la acreditación final formal quedan condicionados a ratificación, aun cuando se muestre el resultado automático. Esta precisión sustituye la interpretación amplia anterior de P16=A sobre efectos operativos definitivos.

## Identidad expediente matrícula y acceso M01 a M09

| Código | Selección | Regla vigente |
|---|---|---|
| M01 | A | Una matrícula por incorporación a generación o escalón. |
| M02 | A | Fórmula común para nuevas emisiones: programa + generación o escalón numérico + año + tipo de participante + consecutivo. |
| M03 | A | C para cadete; I para integrante de mandos. |
| M04 | A | Expediente central de Persona y expediente académico por incorporación. |
| M05 | A | Mantener identificador actual de Usuario. Matrícula como dato académico, sin autenticación ni alias de acceso. |
| M06 | A | Emitir lista inicial en un único acto de confirmación; altas posteriores reciben siguiente consecutivo por orden de registro. |
| M07 | A | Año de inicio de generación o escalón. |
| M08 | A | Mínimo tres dígitos: 001 a 999; después 1000, ampliando sin reiniciar. |
| M09 | A | Lista inicial ordenada por primer apellido, segundo apellido, nombres; desempate por identificador estable de Persona de uso interno. |

Antes del acto inicial, las incorporaciones son propuestas sin matrícula; no son Personas borrador. Solo las incorporaciones confirmadas consumen consecutivo. El orden se fija una vez al confirmar. Una corrección de nombre previa recalcula el orden; después de emitir no reordena ni renumera. El identificador de desempate no aparece en matrícula ni lista emitida.

La secuencia pertenece a la fórmula: programa, generación o escalón, año y tipo. Altas posteriores no reinician ni reordenan. Ampliar la longitud no reformatea matrículas anteriores. No reutilizar matrículas ni consecutivos emitidos tras una baja.

Conservar como antecedente inmutable todas las matrículas ya emitidas, incluyendo Mandos: DMPG2026_…, DMSG2026_…, DMTG2026_…. Registrar el valor histórico completo sin reconstrucción o normalización retroactiva.

Criterio técnico de integridad para el diseño: emisión inicial atómica e idempotente; un fallo no deja emisión parcial y un reintento no duplica matrículas ni consume nuevos consecutivos. La implementación concreta está pendiente.

## Reingreso equivalencias y ámbitos

| Código | Selección | Regla vigente |
|---|---|---|
| C01 | A | Reingreso a otra generación desde el inicio, sujeto a resolución administrativa individual; no recuperar automáticamente estudios anteriores. |
| C02 | A | Resultado automático separado de ratificación, con las excepciones de formalización descritas arriba. |
| C03 | A | Antecedente pendiente no bloquea nueva incorporación autorizada; marcarla «con antecedente pendiente de ratificación» hasta resolver. |
| E01 | A | Administrador o personal administrativo con permiso específico y ámbito asignado autoriza equivalencias. La denominación del cargo no autoriza por sí sola. |
| E02 | A | Conservar origen, destino, resultado reconocido, sustento, actor y fecha; rectificar por nueva versión. No requiere segunda aprobación en esta versión. |
| E03 | A | Asignaciones explícitas por programa, generación o escalón y sede; exigir todas las dimensiones delimitadas. |
| S01 | A | Ratificación con coincidencia de generación y sede cuando ambas estén delimitadas; si solo una lo está, exigir esa dimensión. |

No inferir ámbito institucional completo por ausencia de delimitaciones. Permiso efectivo y DENY/ALLOW se comprueban además del ámbito y de la relación académica. Reingreso no habilita Usuario automáticamente. Baja académica no equivale a baja de Persona. Persona BAJA o INACTIVA mantiene los controles centrales de bloqueo y revocación; Usuario sin Persona no obtiene acceso interactivo.

## Documentación institucional dentro de SIGA

Requisito aprobado: documentar los acuerdos en el sistema con código, redacción vigente, alcance, excepciones, versiones, respaldo, aprobación acreditada, procesos relacionados y estado técnico separado de aprobación funcional. El documento en Git no equivale a haber implementado su consulta en la interfaz.

La presentación concreta «Documentación institucional → Acuerdos funcionales», el catálogo de permisos y el modelo de persistencia siguen como diseño por concretar. Modificar texto documental no cambia automáticamente reglas ejecutables ni permisos.

## Diseño pendiente y límites

- Conciliar entidades, campos, tipos, claves, restricciones y relaciones con el esquema existente. Los nombres lógicos propuestos no son tablas ni contratos aprobados: Persona y Usuario existentes; registro académico, incorporación, matrícula, expediente académico, adscripción, resultados, ratificaciones y equivalencias.
- Reutilizar expediente central existente; no duplicar identidad ni datos personales. La relación académica parte de Persona, no de Usuario; conocer matrícula o identificador no autoriza consultas.
- Precisar serialización de la fórmula nueva, códigos de programa, ancho y representación de generación o escalón y del año, y normalización del orden alfabético. No generar ejemplos nuevos como matrículas oficiales antes de cerrar esos detalles.
- Concretar representación y verificación temporal de ámbitos, permisos específicos y contratos de API. No inventar endpoints publicados.
- Configurar reglas propias de programas de mandos (P01=B): sus escalas e intentos no se heredan automáticamente de cadetes.
- Incorporar el cuestionario institucional original y revisar su versión; P19=A no certifica que ya esté cargado.
- Diseñar la resolución de antecedentes de formadores sustituidos y los controles de privacidad de comentarios y cruces.
- Conservar justificaciones académicas en su evento o registro de dominio; no reincorporar `motivo` a `system.activities`.

## Trazabilidad de esta versión

| Cambio respecto de matriz v1 | Acuerdo vigente |
|---|---|
| Pendientes académicos | P01–P24 respondidos; conservar resto de acuerdos G/F/D/A/R con ajustes indicados. |
| Corrección y baja | Precisiones A06, A12; autorización desde las 12 horas exactas. |
| Evaluación conjunta | P20=B y D06; una respuesta, funciones identificadas, sin conteo duplicado. |
| Ratificación | R03 y C02 precisado; resultado visible separado de formalización. |
| Matrículas | M01–M09 y conservación histórica; emisión inicial conjunta y secuencia por fórmula. |
| Gobierno | C01–C03, E01–E03, S01 aprobados; diseño físico pendiente. |

Para cada implementación posterior registrar requisito, diseño, cambio, validación necesaria, evidencia y aceptación. Esta actualización no declara terminada la auditoría Persona–Usuario ni altera checkpoints históricos.

