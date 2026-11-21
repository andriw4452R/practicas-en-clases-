<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tareas_reportes', function (Blueprint $table) {
            $table->id();
            $table->uuid('message_id')->unique();
            $table->uuid('correlation_id')->index();
            $table->string('modo', 15);
            $table->string('estado', 35)->default('pendiente');
            $table->text('solicitud_json');
            $table->text('resultado')->nullable();
            $table->string('detalle')->nullable();
            $table->double('tiempo_respuesta_ms')->nullable();
            $table->double('tiempo_procesamiento_ms')->nullable();
            $table->timestamp('procesado_en')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('tareas_reportes'); }
};
