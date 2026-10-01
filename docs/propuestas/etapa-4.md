# Propuesta de la Etapa 4: Redacción (sin IA dentro del sistema)

Estado: **aprobada por Alex el 29/09/2026**. El contexto para «Encargar a Claude» llega de las dos formas: la cola «Por redactar» del sistema y el chat.

## Cómo es hoy (caja-rapida.html)

- **15 modelos** escritos dentro del programa:
  - Contratos: compraventa de terreno, compraventa de vehículo, arrendamiento de vivienda, arrendamiento de local comercial, locación de servicios, préstamo de dinero y compromiso de pago. Tienen **3 niveles** (simple, intermedio, avanzado), cláusulas que se prenden o apagan, cláusulas propias y testigos.
  - Cartas y trámites: solicitud, carta poder y autorización.
  - Laboral: carta de renuncia y certificado o constancia de trabajo.
  - Declaraciones juradas: domicilio, convivencia, soltería, ingresos, pérdida de documentos u otra.
  - Currículum en 4 diseños (simple, con foto, Harvard, moderno).
- **Ayudas automáticas:** el formulario pide los datos de cada persona (don/doña, DNI, estado civil, cónyuge, domicilio). El texto sale con montos y fechas en letras, cláusulas numeradas, firmas y huella, y cronograma de cuotas.
- **La IA se usa en 3 lugares:**
  1. Los campos marcados «pulir» (lo que el cliente dice con sus palabras) se reescriben en lenguaje formal.
  2. «Otro documento con IA» redacta un documento completo.
  3. En el CV, redacta el perfil y las funciones.
- **Después de generar:** se corrige el texto tocándolo y sale en PDF o Word. Se cobra en el punto de venta (por página o por documento) y queda en el historial (sin cobrar, cobrado, entregado).
- **«Guardar como modelo»:** convierte un documento hecho en un modelo propio con campos.
- **En tu copia:** 10 documentos hechos (2 compraventas de terreno, 3 currículums, 2 «con IA», 1 locación, 1 renuncia y 1 certificado) y ningún modelo propio. Todos se importan.

## Propuesta: el sistema redacta con modelos, y la redacción nueva la hace Claude

Sin IA dentro del sistema no hay costo por uso, no hace falta una clave de Anthropic en el servidor y funciona aunque se caiga el internet. Lo que antes hacía la IA se reparte así:

### 1. Los modelos pasan a ser datos, no programa

- Los 15 modelos se guardan en la base de datos, con su texto formal, sus campos y sus cláusulas. Se mantienen igual: 3 niveles, cláusulas que se prenden o apagan, montos y fechas en letras, don/doña, cónyuge y testigos.
- Se agrupan en **paquetes por rubro** (imprenta y trámites, inmobiliario, laboral…), pensando en otros negocios.
- Agregar un modelo nuevo ya no obliga a cambiar el programa.

### 2. Frases formales listas, en lugar de «pulir con IA»

- Cada campo donde el cliente habla con sus palabras trae **frases formales para tocar**. Por ejemplo, en una carta poder: «recoger en mi nombre documentos…», «realizar todos los trámites…», «cobrar y recibir…».
- Además, el sistema **ordena el texto solo**: mayúscula inicial, punto final, «Que,» al inicio de cada párrafo en solicitudes y declaraciones, y listas numeradas. Tampoco repite palabras como «me dedico a me dedico a…».
- Después de generar, el texto **se corrige tocándolo**, como ahora.

### 3. «Encargar a Claude» (lo que antes era «Otro documento con IA»)

- **En el mostrador:**
  - Se escribe el contexto con las palabras del cliente: «el señor Pedro le prestó 2000 soles a su cuñado y quiere un papel donde se comprometa a pagarle en 4 meses».
  - También los datos de las personas.
  - Queda en **Redacción › Por redactar**.
- **Cuando tú abras Claude Code** y me digas «redacta los pendientes»:
  - Leo los pendientes en tu sistema.
  - Escribo el texto formal según la ley peruana.
  - Lo guardo en el mismo documento como **«Listo para revisar»**.
- **Tú o Jeremy** lo revisan, corrigen si hace falta, lo imprimen y lo cobran.
- **También sigue sirviendo mandarme el contexto por el chat**, como hoy. Te devuelvo el texto formal y lo guardo directamente en el sistema.
- **Si un tipo de documento se repite**, lo convierto en **modelo nuevo con campos**. Desde ahí sale al instante en el mostrador, sin esperar a Claude.

### 4. Currículum

- Se mantienen los 4 diseños, con foto, en PDF y Word.
- En lugar de la IA, el perfil y las funciones traen **frases de ejemplo por tipo de trabajo** (ventas, atención al cliente, almacén, docencia, construcción, administración) para elegir y ajustar.
- El CV también se puede «Encargar a Claude».

### 5. Lo demás, igual que antes

- Historial con buscador y estados (sin cobrar, cobrado, entregado).
- «Usar como base».
- «Guardar como modelo».
- Cobro en el punto de venta (por página o por documento, y los ejemplares adicionales como impresión).
- PDF y Word.
- Documentos en la ficha del cliente.

## Límites que conviene saber

- «Encargar a Claude» **no es al instante**: se redacta cuando abres Claude Code. Para el cliente que espera en el mostrador sirven los modelos y las frases listas. El encargo conviene para documentos raros o largos que se entregan más tarde.
- Al redactar los pendientes, Claude lee los datos del documento en tu PC (nombres, DNI y domicilios). Sale solo lo necesario para escribir el texto.
- Los modelos son generales, conforme a la legislación peruana. En montos importantes se recomienda al cliente revisarlos con un abogado o notario, como dice el sistema actual.

## Orden propuesto

1. Modelos en la base de datos y el formulario, con los 15 modelos, las frases listas y los documentos importados. ✅
2. Vista del documento: corregir tocando, PDF y Word, cobro e historial. ✅
3. Currículum en 4 diseños. ✅
4. «Encargar a Claude» (por redactar → listo para revisar) y «Guardar como modelo».
