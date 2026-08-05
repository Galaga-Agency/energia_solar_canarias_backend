<?php

require_once __DIR__ . '/../models/conexion.php';

class PlantasAsociadasDB
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = Conexion::getInstance();
    }

    /**
     * Relacionar un usuario con una planta
     * 
     * @param int $idPlanta El ID de la planta
     * @param int $idUsuario El ID del usuario
     * @param string $proveedor El nombre del proveedor
     * @return array en caso de éxito o false en caso de error
     */
    public function getPlantasAsociadasAlUsuario($idUsuario)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $query = "SELECT p.nombre AS nombre_proveedor, pa.planta_id as planta_id FROM plantas_asociadas pa JOIN proveedores p ON pa.proveedor_id = p.id WHERE pa.usuario_id = ?;";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Vincula el parámetro 'i' para enteros
            $stmt->bind_param('i', $idUsuario);

            // Ejecuta la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
                return false;
            }

            // Recoge los resultados de la consulta
            $result = $stmt->get_result();
            $plantas = [];
            while ($row = $result->fetch_assoc()) {
                $plantas[] = $row;
            }

            // Cierra la consulta y la conexión
            $stmt->close();
            // Devuelve el array de plantas asociadas
            return $plantas;
        } catch (Exception $e) {
            error_log("Error al relacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Usuarios que tienen acceso a una planta.
     *
     * Es la consulta inversa de getPlantasAsociadasAlUsuario(): aquella responde
     * "que plantas ve este usuario", y esta "quien ve esta planta". El frontend
     * la necesita para la ficha de la planta; sin ella habria que pedir todos
     * los usuarios y sus plantas para pintar un solo panel.
     *
     * No devuelve password_hash ni ningun otro campo sensible: solo lo que hace
     * falta para listar personas.
     *
     * @param string $idPlanta  Id de planta del proveedor (varchar, no entero).
     * @param string $proveedor Nombre del proveedor, p.ej. "GoodWe".
     * @return array|false
     */
    public function getUsuariosDeLaPlanta($idPlanta, $proveedor = null)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // El proveedor es opcional: dos proveedores podrian usar el mismo id
            // de planta, asi que cuando se conoce se filtra, y cuando no, se
            // devuelven todas las coincidencias.
            if ($proveedor !== null && $proveedor !== '') {
                $query = "SELECT u.usuario_id, u.email, u.nombre, u.apellido, u.imagen,
                                 c.nombre AS clase
                          FROM plantas_asociadas pa
                          JOIN usuarios u ON u.usuario_id = pa.usuario_id
                          LEFT JOIN clases c ON c.clase_id = u.clase_id
                          JOIN proveedores p ON p.id = pa.proveedor_id
                          WHERE pa.planta_id = ? AND p.nombre = ?
                            AND (u.eliminado IS NULL OR u.eliminado = 0)
                          ORDER BY u.nombre, u.apellido;";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                }
                $stmt->bind_param('ss', $idPlanta, $proveedor);
            } else {
                $query = "SELECT u.usuario_id, u.email, u.nombre, u.apellido, u.imagen,
                                 c.nombre AS clase
                          FROM plantas_asociadas pa
                          JOIN usuarios u ON u.usuario_id = pa.usuario_id
                          LEFT JOIN clases c ON c.clase_id = u.clase_id
                          WHERE pa.planta_id = ?
                            AND (u.eliminado IS NULL OR u.eliminado = 0)
                          ORDER BY u.nombre, u.apellido;";
                $stmt = $conn->prepare($query);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                }
                $stmt->bind_param('s', $idPlanta);
            }

            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $usuarios = [];
            while ($row = $result->fetch_assoc()) {
                $usuarios[] = $row;
            }
            $stmt->close();

            return $usuarios;
        } catch (Exception $e) {
            error_log("Error al obtener usuarios de la planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verificar si una planta está asociada a un usuario.
     * 
     * @param int $usuarioId El ID del usuario
     * @param int $idPlanta El ID de la planta
     * @param string $proveedor El nombre del proveedor
     * @return bool true en caso de éxito o false en caso de error
     */
    public function isPlantasAsociadasAlUsuario($usuarioId, $idPlanta, $proveedor)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $query = "SELECT * FROM plantas_asociadas WHERE usuario_id = ? AND planta_id = ? AND proveedor_id = (SELECT proveedores.id from proveedores WHERE proveedores.nombre = ?);";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Vincula los parámetros
            $stmt->bind_param('iss', $usuarioId, $idPlanta, $proveedor);

            // Ejecuta la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }

            // Recoge el resultado
            $result = $stmt->get_result();

            // Devuelve true si se encontró una fila, false en caso contrario
            $existeAsociacion = $result->num_rows > 0;

            // Cierra la consulta y la conexión
            $stmt->close();

            return $existeAsociacion;
        } catch (Exception $e) {
            error_log("Error al verificar la asociación entre usuario y planta: " . $e->getMessage());
            return false;
        }
    }
}
